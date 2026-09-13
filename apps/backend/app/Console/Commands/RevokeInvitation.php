<?php

namespace App\Console\Commands;

use App\Services\InvitationService;
use Illuminate\Console\Command;

class RevokeInvitation extends Command
{
    protected $signature = 'invitation:revoke {id}';

    protected $description = 'Revoke a registration invitation.';

    public function handle(InvitationService $invitations): int
    {
        try {
            $invitations->revoke((string) $this->argument('id'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Invitation revoked.');

        return self::SUCCESS;
    }
}
