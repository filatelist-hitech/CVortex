<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Console\Command;

class DisableUser extends Command
{
    protected $signature = 'user:disable {id}';

    protected $description = 'Disable a user and invalidate their active sessions.';

    public function handle(UserStatusService $status): int
    {
        try {
            $status->disable(User::query()->findOrFail((string) $this->argument('id')));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('User disabled.');

        return self::SUCCESS;
    }
}
