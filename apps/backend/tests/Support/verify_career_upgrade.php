<?php

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\Models\CareerFact;
use App\Models\CareerSource;
use App\Models\User;
use App\Services\CareerExtractionService;
use App\Services\CareerFactService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$legacyUser = User::query()->where('email', 'legacy-career@example.test')->sole();
$legacySource = CareerSource::query()->where('owner_id', $legacyUser->id)->sole();
$legacyFact = CareerFact::query()->where('owner_id', $legacyUser->id)->sole();
if ($legacySource->source_text !== 'Legacy synthetic source.'
    || $legacySource->kind !== 'PASTED_TEXT'
    || $legacyFact->assertion_original !== 'Legacy synthetic fact.'
    || $legacyFact->status !== CareerFact::STATUS_CONFIRMED) {
    throw new RuntimeException('Legacy Career data was not reconciled correctly.');
}

$manual = app(CareerFactService::class)->createManual($legacyUser, 'skill', 'Manual fact after upgrade.');
if ($manual->status !== CareerFact::STATUS_CONFIRMED) {
    throw new RuntimeException('Manual fact creation failed after upgrade.');
}

$app->instance(LlmProvider::class, new class implements LlmProvider
{
    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return new LlmResponse(['facts' => [[
            'fact_type' => 'experience',
            'assertion' => 'Extraction after upgrade.',
            'source_excerpt' => 'Extraction after upgrade.',
            'confidence' => 0.8,
        ]]], 'synthetic', 'upgrade-test-model', 4, 2, 1, 'req-upgrade');
    }
});
$source = app(CareerExtractionService::class)->extract($legacyUser, 'Extraction after upgrade.');
if ($source->extraction_status !== CareerSource::STATUS_COMPLETED
    || ! CareerFact::query()->where('career_source_id', $source->id)->where('status', CareerFact::STATUS_PENDING)->exists()) {
    throw new RuntimeException('Extraction failed after upgrade.');
}

if (DB::table('llm_runs')->where('career_source_id', $source->id)->value('validation_result') !== 'PASS') {
    throw new RuntimeException('LlmRun metadata is incomplete after upgrade.');
}

echo "career-upgrade-runtime: PASS\n";
