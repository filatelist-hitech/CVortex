<?php

namespace App\Console\Commands;

use App\Diagnostics\ConsoleFailureReporter;
use App\Services\InvitationService;
use Illuminate\Console\Command;

class RevokeInvitation extends Command
{
    protected $signature = 'invitation:revoke {id}';

    protected $description = 'Revoke a registration invitation.';

    public function handle(InvitationService $invitations, ConsoleFailureReporter $failures): int
    {
        try {
            $invitations->revoke((string) $this->argument('id'));
        } catch (\Throwable $exception) {
            if (($message = $failures->expectedMessage($exception)) !== null) {
                $this->error($message);

                return self::FAILURE;
            }
            $reference = $failures->report($exception, (string) $this->getName());
            $this->error('Command failed. Reference: '.$reference);

            return self::FAILURE;
        }
        $this->info('Invitation revoked.');

        return self::SUCCESS;
    }
}
