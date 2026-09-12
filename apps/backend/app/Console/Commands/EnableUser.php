<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Console\Command;

class EnableUser extends Command
{
    protected $signature = 'user:enable {id}';

    protected $description = 'Enable a disabled user.';

    public function handle(UserStatusService $status): int
    {
        try {
            $status->enable(User::query()->findOrFail((string) $this->argument('id')));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('User enabled.');

        return self::SUCCESS;
    }
}
