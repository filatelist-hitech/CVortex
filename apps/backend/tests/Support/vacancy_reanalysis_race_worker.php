<?php

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\Jobs\AnalyzeVacancy;
use App\Jobs\ExtractCareerSource;
use App\Models\CareerSource;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancySnapshot;
use App\Services\CareerExtractionService;
use App\Services\CareerFactService;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisService;
use App\Services\VacancyIngestionService;
use App\Services\VacancyReanalysisService;
use Illuminate\Bus\UniqueLock;
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

if (in_array($action, ['reanalyze-a', 'import-b', 'analyze-pause', 'recover-a', 'career-recover-a'], true)) {
    $paused = false;
    DB::listen(function ($query) use ($action, &$paused): void {
        $sql = strtolower($query->sql);
        $boundary = match ($action) {
            'reanalyze-a' => str_contains($sql, 'vacancy_snapshots') && str_contains($sql, 'select'),
            'import-b' => str_contains($sql, 'vacancies') && str_contains($sql, 'for update'),
            'recover-a' => str_contains($sql, 'vacancies') && str_contains($sql, 'for update'),
            'career-recover-a' => str_contains($sql, 'career_sources') && str_contains($sql, 'for update'),
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

if ($action === 'seed-recovery') {
    $context->run($ownerId, function () use ($owner, $sourceUrl): void {
        $vacancy = Vacancy::query()->where('owner_id', $owner->id)->where('source_url', $sourceUrl)->sole();
        $snapshot = VacancySnapshot::query()->where('owner_id', $owner->id)->where('vacancy_id', $vacancy->id)->orderByDesc('version')->firstOrFail();
        $vacancy->forceFill([
            'analysis_status' => Vacancy::STATUS_PENDING,
            'next_attempt_at' => now()->subMinutes(10),
            'dispatch_recovery_at' => now()->subMinutes(7),
        ])->save();
        DB::table('vacancies')->where('id', $vacancy->id)->update(['updated_at' => now()->subMinutes(10)]);
        if (! app(UniqueLock::class)->acquire(new AnalyzeVacancy((string) $owner->id, (string) $snapshot->id))) {
            throw new RuntimeException('Could not seed the orphan Vacancy unique lock.');
        }

        $profile = app(CareerFactService::class)->profileFor($owner);
        $text = 'Synthetic Career orphan recovery.';
        $source = CareerSource::query()->create([
            'owner_id' => $owner->id,
            'career_profile_id' => $profile->id,
            'kind' => 'PASTED_TEXT',
            'source_text' => $text,
            'content_hash' => hash('sha256', $text),
            'extraction_status' => CareerSource::STATUS_PENDING,
        ]);
        $source->forceFill([
            'next_attempt_at' => now()->subMinutes(10),
            'dispatch_recovery_at' => now()->subMinutes(7),
        ])->save();
        DB::table('career_sources')->where('id', $source->id)->update(['updated_at' => now()->subMinutes(10)]);
        if (! app(UniqueLock::class)->acquire(new ExtractCareerSource((string) $owner->id, (string) $source->id))) {
            throw new RuntimeException('Could not seed the orphan Career unique lock.');
        }
        echo 'vacancy='.$vacancy->id.' snapshot='.$snapshot->id.' career_source='.$source->id.PHP_EOL;
    });
} elseif (str_starts_with($action, 'career-recover')) {
    $result = app(CareerExtractionService::class)->queue($owner, 'Synthetic Career orphan recovery.');
    echo 'source='.$result->id.' jobs='.count(Queue::pushed(ExtractCareerSource::class)).PHP_EOL;
} elseif (str_starts_with($action, 'recover')) {
    $result = app(VacancyReanalysisService::class)->queue($owner, $argument);
    echo 'snapshot='.$result['snapshot']->id.' status='.$result['status'].' jobs='.count(Queue::pushed(AnalyzeVacancy::class)).PHP_EOL;
} elseif (str_starts_with($action, 'reanalyze')) {
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
