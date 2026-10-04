<?php

use App\AI\ChatGpt\ConnectionService;
use App\Models\CareerFact;
use App\Models\ChatGptConnection;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancyAnalysisDraft;
use App\Models\VacancyAnalysisDraftRequirement;
use App\Models\VacancyChatMessage;
use App\Models\VacancyChatThread;
use App\Models\VacancyMatchDimension;
use App\Models\VacancyMatchEvidence;
use App\Models\VacancyRequirement;
use App\Models\VacancySnapshot;
use App\Services\CareerFactService;
use App\Services\DatabaseOwnerContext;
use App\Services\VacancyAnalysisDraftService;
use App\Services\VacancyChatService;
use App\Services\VacancyIngestionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): never {
    fwrite(STDERR, 'Boundary failure ['.($argv[1] ?? 'unknown').']: '.get_class($exception).' code '.$exception->getCode().' at line '.$exception->getLine().' '.($exception->getPrevious()?->getMessage() ?? '').PHP_EOL);
    exit(1);
});
config(['chatgpt.enabled' => true]);
$owners = app(DatabaseOwnerContext::class);
$mode = $argv[1] ?? '';
$counter = $argv[2] ?? '';

if ($mode === 'setup') {
    $role = DB::selectOne('SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');
    assert($role->rolsuper === false && $role->rolbypassrls === false);
    $flags = DB::selectOne("SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = 'chatgpt_connections'");
    assert($flags->relrowsecurity && $flags->relforcerowsecurity);
    $owner = User::query()->create(['email' => 'chatgpt-owner@example.test', 'password' => 'synthetic-test-password'])->fresh();
    $other = User::query()->create(['email' => 'chatgpt-other@example.test', 'password' => 'synthetic-test-password'])->fresh();
    $connection = $owners->run($owner->id, fn () => ChatGptConnection::query()->create([
        'owner_id' => $owner->id, 'client_id' => 'oaiapp_fixture', 'subject' => 'subject-fixture',
        'scopes' => ['chatgpt.tokens.use.direct', 'resource.invoke'], 'status' => 'CONNECTED',
        'access_token' => 'synthetic-expired', 'refresh_token' => 'synthetic-original', 'expires_at' => now()->subMinute(),
    ]));
    assert(DB::table('chatgpt_connections')->count() === 0);
    $owners->run($other->id, function () use ($connection, $owner): void {
        assert(ChatGptConnection::query()->find($connection->id) === null);
        assert(DB::table('chatgpt_connections')->where('id', $connection->id)->update(['status' => 'NOT_CONNECTED']) === 0);
        try {
            ChatGptConnection::query()->create(['owner_id' => $owner->id, 'client_id' => 'oaiapp_forged', 'scopes' => []]);
            throw new RuntimeException('Cross-owner insert must fail.');
        } catch (QueryException $exception) {
            assert($exception->getCode() === '42501');
        }
    });
    Queue::fake();
    $vacancy = app(VacancyIngestionService::class)->queue($owner, 'Engineer. PHP is required.', null)['vacancy'];
    $owners->run($owner->id, function () use ($owner, $vacancy): void {
        $vacancy->update(['analysis_status' => 'FAILED']);
        app(CareerFactService::class)->createManual($owner, 'skill', 'Production PHP development.');
        $thread = app(VacancyChatService::class)->open($owner, $vacancy->id);
        VacancyChatMessage::query()->create(['owner_id' => $owner->id, 'thread_id' => $thread->id,
            'vacancy_snapshot_id' => VacancySnapshot::query()->value('id'),
            'career_signature' => 'synthetic-signature', 'role' => 'user', 'content' => 'synthetic', 'status' => 'COMPLETED']);
    });
    foreach (['vacancy_chat_threads', 'vacancy_chat_messages', 'vacancy_analysis_drafts', 'vacancy_analysis_draft_requirements'] as $table) {
        $flags = DB::selectOne('SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE relname = ?', [$table]);
        assert($flags->relrowsecurity && $flags->relforcerowsecurity);
        assert(DB::table($table)->count() === 0);
        $owners->run($other->id, fn () => assert(DB::table($table)->count() === 0));
    }
    $owners->run($other->id, function () use ($other, $connection, $vacancy): void {
        try {
            VacancyChatThread::query()->create(['owner_id' => $other->id, 'vacancy_id' => $vacancy->id,
                'connection_id' => $connection->id, 'provider' => 'openai_chatgpt_plan', 'status' => 'IDLE']);
            throw new RuntimeException('Cross-owner composite relation must fail.');
        } catch (QueryException $exception) {
            assert($exception->getCode() === '23503');
        }
    });
    file_put_contents($counter, '0');
    echo "RLS and runtime-role isolation: PASS\n";
} elseif ($mode === 'refresh') {
    $owner = User::query()->where('email', 'chatgpt-owner@example.test')->firstOrFail();
    $connection = $owners->run($owner->id, fn () => ChatGptConnection::query()->firstOrFail());
    Http::preventStrayRequests();
    Http::fake([config('chatgpt.token_url') => function ($request) use ($counter) {
        assert($request['refresh_token'] === 'synthetic-original');
        $file = fopen($counter, 'c+');
        flock($file, LOCK_EX);
        $count = (int) stream_get_contents($file);
        rewind($file);
        fwrite($file, (string) ($count + 1));
        fflush($file);
        flock($file, LOCK_UN);
        fclose($file);
        usleep(300000);

        return Http::response(['access_token' => 'synthetic-replacement', 'refresh_token' => 'synthetic-rotated',
            'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'resource.invoke chatgpt.tokens.use.direct']);
    }]);
    assert(app(ConnectionService::class)->accessToken($owner, $connection->id) === 'synthetic-replacement');
    echo "refresh worker: PASS\n";
} elseif ($mode === 'draft') {
    $owner = User::query()->where('email', 'chatgpt-owner@example.test')->firstOrFail();
    $owners->run($owner->id, function () use ($owner): void {
        $vacancy = Vacancy::query()->firstOrFail();
        $snapshot = VacancySnapshot::query()->firstOrFail();
        $saved = app(VacancyAnalysisDraftService::class)->save($owner, $vacancy->id, $snapshot->id,
            'parallel-draft', ['requirements' => [['dimension' => 'TECHNICAL', 'importance' => 'MANDATORY',
                'label' => 'PHP', 'normalized_value' => 'PHP', 'source_excerpt' => 'PHP is required.', 'confidence' => 1]],
                'matches' => [], 'gaps' => [], 'risks' => [], 'questions' => [], 'recommendations' => []]);
        assert($saved['status'] === 'DRAFT');
    });
    echo "draft worker: PASS\n";
} elseif ($mode === 'draft-verify') {
    $owner = User::query()->where('email', 'chatgpt-owner@example.test')->firstOrFail();
    $owners->run($owner->id, function () use ($owner): void {
        assert(VacancyAnalysisDraft::query()->count() === 1);
        assert(VacancyAnalysisDraftRequirement::query()->count() === 1);
        assert(CareerFact::query()->where('owner_id', $owner->id)->where('status', 'CONFIRMED')->count() === 1);
        assert(VacancySnapshot::query()->value('raw_text') === 'Engineer. PHP is required.');
    });
    echo "Concurrent draft idempotency and source/fact preservation: PASS\n";
} elseif ($mode === 'approval-setup') {
    $tableOwner = DB::selectOne("SELECT pg_get_userbyid(relowner) AS name FROM pg_class WHERE relname = 'vacancy_analyses'");
    if ($tableOwner === null || $tableOwner->name !== DB::selectOne('SELECT current_user AS name')->name) {
        throw new RuntimeException('Approval race trigger must be installed by the vacancy_analyses owner.');
    }
    DB::unprepared(<<<'SQL'
CREATE FUNCTION chatgpt_test_delay_analysis_insert() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    PERFORM pg_sleep(2);
    RETURN NEW;
END;
$$
SQL);
    DB::unprepared('CREATE TRIGGER chatgpt_test_delay_analysis_insert BEFORE INSERT ON vacancy_analyses FOR EACH ROW EXECUTE FUNCTION chatgpt_test_delay_analysis_insert()');
    echo "Approval overlap barrier installed by table owner: PASS\n";
} elseif ($mode === 'approval') {
    $owner = User::query()->where('email', 'chatgpt-owner@example.test')->firstOrFail();
    $appName = 'cvortex-chatgpt-approval-'.($argv[2] ?? 'worker');
    DB::selectOne("SELECT set_config('application_name', ?, false)", [$appName]);
    $analysisId = $owners->run($owner->id, function () use ($owner): string {
        $draft = VacancyAnalysisDraft::query()->firstOrFail();
        $result = app(VacancyAnalysisDraftService::class)->approve($owner, $draft->id);
        assert($result['status'] === 'APPROVED');
        assert(VacancyAnalysisDraft::query()->findOrFail($draft->id)->approved_analysis_id === $result['approved_analysis_id']);

        return $result['approved_analysis_id'];
    });
    assert(DB::transactionLevel() === 0);
    assert((int) DB::selectOne('SELECT 1 AS value')->value === 1);
    echo 'APPROVAL status=APPROVED analysis='.$analysisId." transaction=clean\n";
} elseif ($mode === 'approval-verify') {
    $owner = User::query()->where('email', 'chatgpt-owner@example.test')->firstOrFail();
    $owners->run($owner->id, function () use ($owner): void {
        $draft = VacancyAnalysisDraft::query()->firstOrFail();
        $fact = CareerFact::query()->where('owner_id', $owner->id)->where('status', 'CONFIRMED')->firstOrFail();
        $analyses = VacancyAnalysis::query()->where('owner_id', $owner->id)->where('vacancy_snapshot_id', $draft->vacancy_snapshot_id)->get();
        assert($draft->status === 'APPROVED');
        assert($analyses->count() === 1);
        $analysis = $analyses->firstOrFail();
        assert($draft->approved_analysis_id === $analysis->id);
        assert($analysis->career_signature === $draft->career_signature);
        assert(VacancyAnalysisDraftRequirement::query()->where('owner_id', $owner->id)->where('draft_id', $draft->id)->count() === 1);
        assert(VacancyRequirement::query()->where('owner_id', $owner->id)->where('vacancy_snapshot_id', $draft->vacancy_snapshot_id)->count() === 1);
        assert(VacancyMatchDimension::query()->where('owner_id', $owner->id)->where('vacancy_analysis_id', $analysis->id)->count() === count(VacancyRequirement::DIMENSIONS));
        $evidence = VacancyMatchEvidence::query()->where('owner_id', $owner->id)->where('career_fact_id', $fact->id)->get();
        assert($evidence->count() === 1);
        $dimension = VacancyMatchDimension::query()->where('owner_id', $owner->id)->findOrFail($evidence->firstOrFail()->vacancy_match_dimension_id);
        assert($dimension->vacancy_analysis_id === $analysis->id);
        assert($dimension->dimension === 'TECHNICAL' && $dimension->result === 'MATCH');
        assert($fact->assertion_approved === 'Production PHP development.');
        assert(CareerFact::query()->where('owner_id', $owner->id)->count() === 1);
        assert(VacancySnapshot::query()->where('owner_id', $owner->id)->value('raw_text') === 'Engineer. PHP is required.');
        assert(Vacancy::query()->where('owner_id', $owner->id)->value('analysis_status') === 'COMPLETED');
    });
    assert(DB::transactionLevel() === 0);
    assert((int) DB::selectOne('SELECT 1 AS value')->value === 1);
    echo "Approval canonical analysis, current confirmed fact matching and single-state invariants: PASS\n";
} elseif ($mode === 'verify') {
    assert(trim(file_get_contents($counter)) === '1');
    $owner = User::query()->where('email', 'chatgpt-owner@example.test')->firstOrFail();
    $owners->run($owner->id, function (): void {
        $connection = ChatGptConnection::query()->firstOrFail();
        assert($connection->refresh_token === 'synthetic-rotated');
        assert($connection->access_token === 'synthetic-replacement');
        assert(DB::table('chatgpt_connections')->value('refresh_token') !== 'synthetic-rotated');
    });
    echo "Concurrent refresh: one exchange, intact encrypted token chain: PASS\n";
} else {
    throw new RuntimeException('Unknown test mode.');
}
