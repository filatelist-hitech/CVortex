<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BootstrapFirstAdmin extends Command
{
    private const BOOTSTRAP_LOCK_NAMESPACE = 1129735762;

    private const BOOTSTRAP_LOCK_KEY = 1;

    protected $signature = 'user:bootstrap-admin {email}';

    protected $description = 'Create the first active admin account.';

    public function handle(EmailNormalizer $emails, AuditLogger $audit): int
    {
        $password = $this->secret('Password (15-128 characters)');
        if (! is_string($password) || mb_strlen($password) < 15 || mb_strlen($password) > 128) {
            $this->error('Password must contain 15 to 128 characters.');

            return self::FAILURE;
        }
        try {
            DB::transaction(function () use ($emails, $audit, $password): void {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select(
                        'SELECT pg_advisory_xact_lock(?, ?)',
                        [self::BOOTSTRAP_LOCK_NAMESPACE, self::BOOTSTRAP_LOCK_KEY],
                    );
                }

                if (User::query()->where('role', User::ROLE_ADMIN)->exists()) {
                    throw new \RuntimeException('An admin account already exists.');
                }
                $user = new User;
                $user->forceFill([
                    'email' => $emails->validate((string) $this->argument('email')),
                    'password' => Hash::make($password),
                    'role' => User::ROLE_ADMIN,
                    'status' => User::STATUS_ACTIVE,
                ])->save();
                $audit->record('user.bootstrap_admin', AuditEvent::ACTOR_OPERATOR, null, User::class, $user->id);
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('First admin created.');

        return self::SUCCESS;
    }
}
