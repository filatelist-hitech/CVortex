<?php

namespace Tests\Feature;

use App\Diagnostics\IncidentRecorder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DiagnosticsPostgresTest extends TestCase
{
    use DatabaseTransactions;

    public function test_runtime_role_can_group_and_admin_can_query_without_user_access(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL runtime-role boundary only.');
        }
        $role = DB::selectOne('SELECT current_user AS role')->role;
        $this->assertSame(config('database.runtime_role'), $role);
        $admin = $this->user('admin');
        $other = $this->user('user');
        $reference = 'req_'.Str::ulid();
        $recorder = app(IncidentRecorder::class);
        $recorder->record('INTERNAL_ERROR', 'Safe message', 'api', context: ['request_id' => $reference, 'user_id' => $other->id]);
        $recorder->record('INTERNAL_ERROR', 'Safe message', 'api', context: ['request_id' => $reference, 'user_id' => $other->id]);
        $incidentId = DB::table('diagnostic_occurrences')->where('request_id', $reference)->value('incident_id');
        $this->assertNotNull($incidentId);
        $this->assertSame(2, DB::table('diagnostic_incidents')->where('id', $incidentId)->value('occurrence_count'));
        $this->assertSame(2, DB::table('diagnostic_occurrences')->where('incident_id', $incidentId)->count());
        $this->as($other)->getJson('/api/v1/diagnostics/incidents?search='.$reference)->assertForbidden();
        $this->as($admin)->getJson('/api/v1/diagnostics/incidents?search='.$reference)->assertOk()->assertJsonPath('data.total', 1);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['email' => Str::ulid().'@example.test', 'password' => Hash::make('test password long enough')]);
        $user->forceFill(['role' => $role])->save();

        return $user->fresh();
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web')->withSession(['auth_generation' => $user->auth_generation]);
    }
}
