<?php

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\Jobs\AnalyzeVacancy;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancySnapshot;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisService;
use App\Services\VacancyIngestionService;
use App\Services\VacancyReanalysisService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

[$script, $action, $ownerId, $sourceUrl, $argument] = $argv;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Queue::fake();
$app->instance(LlmProvider::class, new class implements LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return new LlmResponse(['requirements' => []], 'race-fixture', 'race-fixture', 1, 1, 1, 'race-fixture', 0);
    }
});
DB::statement("SET application_name = 'cvortex-race-".$action."'");
$owner = User::query()->findOrFail($ownerId);
$context = app(DatabaseOwnerContext::class);

if (in_array($action, ['reanalyze-a', 'import-b', 'analyze-pause'], true)) {
    $paused = false;
    DB::listen(function ($query) use ($action, &$paused): void {
        $sql = strtolower($query->sql);
        $boundary = match ($action) {
            'reanalyze-a' => str_contains($sql, 'vacancy_snapshots') && str_contains($sql, 'select'),
            'import-b' => str_contains($sql, 'vacancies') && str_contains($sql, 'for update'),
            default => str_contains($sql, 'vacancies') && str_contains($sql, 'update') && str_contains($sql, 'analysis_status'),
        };
        if ($paused || ! $boundary) {
            return;
        }
        $paused = true;
        echo "paused\n";
        flush();
        $deadline = microtime(true) + 30;
        while (! DB::table('vacancy_reanalysis_test_barrier')->where('name', $action)->value('released')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Race barrier timed out.');
            }
            usleep(20000);
        }
    });
}

if (str_starts_with($action, 'reanalyze')) {
    $result = app(VacancyReanalysisService::class)->queue($owner, $argument);
    echo 'snapshot='.$result['snapshot']->id.' status='.$result['status'].PHP_EOL;
} elseif (str_starts_with($action, 'import')) {
    $result = app(VacancyIngestionService::class)->queue($owner, $argument, $sourceUrl);
    app(VacancyAnalysisService::class)->analyze($owner, $result['snapshot']);
    echo 'snapshot='.$result['snapshot']->id.PHP_EOL;
} elseif ($action === 'stale') {
    (new AnalyzeVacancy($ownerId, $argument))->handle(app(VacancyAnalysisService::class), $context);
    echo "stale-job-executed\n";
} elseif (in_array($action, ['analyze', 'analyze-pause'], true)) {
    $context->run($ownerId, function () use ($owner, $argument): void {
        $snapshot = VacancySnapshot::query()->where('owner_id', $owner->id)->findOrFail($argument);
        app(VacancyAnalysisService::class)->analyze($owner, $snapshot);
    });
    echo "analysis-completed\n";
} elseif ($action === 'current') {
    $context->run($ownerId, function () use ($owner, $sourceUrl): void {
        $vacancy = Vacancy::query()->where('owner_id', $owner->id)->where('source_url', $sourceUrl)->sole();
        $snapshot = VacancySnapshot::query()->where('vacancy_id', $vacancy->id)->orderByDesc('version')->firstOrFail();
        echo 'vacancy='.$vacancy->id.' snapshot='.$snapshot->id.' status='.$vacancy->analysis_status.PHP_EOL;
    });
} elseif ($action === 'verify') {
    $context->run($ownerId, function () use ($owner, $sourceUrl, $argument): void {
        $vacancy = Vacancy::query()->where('owner_id', $owner->id)->where('source_url', $sourceUrl)->sole();
        $snapshot = VacancySnapshot::query()->where('vacancy_id', $vacancy->id)->orderByDesc('version')->firstOrFail();
        if ($snapshot->id !== $argument || $vacancy->analysis_status !== Vacancy::STATUS_COMPLETED
            || ! VacancyAnalysis::query()->where('vacancy_snapshot_id', $snapshot->id)->exists()) {
            throw new RuntimeException('Race left the current aggregate without its completed analysis.');
        }
        echo 'verified-current='.$snapshot->id.' status='.$vacancy->analysis_status.PHP_EOL;
    });
}
