<?php

namespace App\Console\Commands;

use App\Diagnostics\ConsoleFailureReporter;
use App\Services\InvitationService;
use Illuminate\Console\Command;

class CreateInvitation extends Command
{
    protected $signature = 'invitation:create {--email= : Target email; omit for a generic invitation} {--expires=7 : Expiry in days (1-30)}';

    protected $description = 'Create a one-time registration invitation and reveal its token once.';

    public function handle(InvitationService $invitations, ConsoleFailureReporter $failures): int
    {
        try {
            ['invitation' => $invitation, 'token' => $token] = $invitations->create($this->option('email'), (int) $this->option('expires'));
        } catch (\Throwable $exception) {
            if (($message = $failures->expectedMessage($exception)) !== null) {
                $this->error($message);

                return self::FAILURE;
            }
            $reference = $failures->report($exception, (string) $this->getName());
            $this->error('Command failed. Reference: '.$reference);

            return self::FAILURE;
        }
        $this->line('Invitation ULID: '.$invitation->id);
        $this->line('Invitation URL (show once): /register#token='.$token);

        return self::SUCCESS;
    }
}
