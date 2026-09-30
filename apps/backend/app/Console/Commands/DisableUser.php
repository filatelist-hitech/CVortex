<?php

namespace App\Console\Commands;

use App\Diagnostics\ConsoleFailureReporter;
use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Console\Command;

class DisableUser extends Command
{
    protected $signature = 'user:disable {id}';

    protected $description = 'Disable a user and invalidate their active sessions.';

    public function handle(UserStatusService $status, ConsoleFailureReporter $failures): int
    {
        try {
            $status->disable(User::query()->findOrFail((string) $this->argument('id')));
        } catch (\Throwable $exception) {
            if (($message = $failures->expectedMessage($exception)) !== null) {
                $this->error($message);

                return self::FAILURE;
            }
            $reference = $failures->report($exception, (string) $this->getName());
            $this->error('Command failed. Reference: '.$reference);

            return self::FAILURE;
        }
        $this->info('User disabled.');

        return self::SUCCESS;
    }
}
