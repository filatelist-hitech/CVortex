<?php

use App\Models\CareerFact;
use App\Models\CareerProfile;
use App\Models\CareerSource;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$user = User::query()->create(['email' => 'review-race@example.test', 'password' => 'synthetic-password']);
$profile = CareerProfile::query()->create(['owner_id' => $user->id]);
$text = 'Concurrent pending review assertion.';
$source = CareerSource::query()->create([
    'owner_id' => $user->id, 'career_profile_id' => $profile->id, 'kind' => 'PASTED_TEXT',
    'source_text' => $text, 'content_hash' => hash('sha256', $text), 'extraction_status' => CareerSource::STATUS_COMPLETED,
]);
$fact = CareerFact::query()->create([
    'owner_id' => $user->id, 'career_profile_id' => $profile->id, 'career_source_id' => $source->id,
    'provenance_type' => CareerFact::PROVENANCE_EXTRACTION, 'fact_type' => 'experience',
    'assertion_original' => $text, 'source_excerpt' => $text, 'extracted_by' => 'test@1.0.0',
    'candidate_hash' => hash('sha256', $text), 'status' => CareerFact::STATUS_PENDING,
]);
echo $user->id.PHP_EOL.$fact->id.PHP_EOL;
