<?php

namespace App\Console\Commands;

use App\Diagnostics\ConsoleFailureReporter;
use App\Models\User;
use App\Services\UserStatusService;
use Illuminate\Console\Command;

class EnableUser extends Command
{
    protected $signature = 'user:enable {id}';

    protected $description = 'Enable a disabled user.';

    public function handle(UserStatusService $status, ConsoleFailureReporter $failures): int
    {
        try {
            $status->enable(User::query()->findOrFail((string) $this->argument('id')));
        } catch (\Throwable $exception) {
            if (($message = $failures->expectedMessage($exception)) !== null) {
                $this->error($message);

                return self::FAILURE;
            }
            $reference = $failures->report($exception, (string) $this->getName());
            $this->error('Command failed. Reference: '.$reference);

            return self::FAILURE;
        }
        $this->info('User enabled.');

        return self::SUCCESS;
    }
}
