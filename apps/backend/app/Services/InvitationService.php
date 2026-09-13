<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(private readonly EmailNormalizer $emails, private readonly AuditLogger $audit) {}

    /** @return array{invitation: Invitation, token: string} */
    public function create(?string $targetEmail, int $expiresInDays): array
    {
        if ($expiresInDays < 1 || $expiresInDays > 30) {
            throw ValidationException::withMessages(['expires' => 'Expiry must be between 1 and 30 days.']);
        }

        $normalizedTargetEmail = $targetEmail === null ? null : $this->emails->validate($targetEmail);
        $token = bin2hex(random_bytes(32));
        $invitation = DB::transaction(function () use ($normalizedTargetEmail, $token, $expiresInDays): Invitation {
            $invitation = Invitation::query()->create([
                'token_hash' => $this->tokenHash($token),
                'target_email' => $normalizedTargetEmail,
                'expires_at' => now()->addDays($expiresInDays),
            ]);
            $this->audit->record('invitation.created', AuditEvent::ACTOR_OPERATOR, null, Invitation::class, $invitation->id);

            return $invitation;
        });

        return compact('invitation', 'token');
    }

    public function revoke(string $id): Invitation
    {
        return DB::transaction(function () use ($id): Invitation {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($id);
            if ($invitation->revoked_at !== null) {
                throw ValidationException::withMessages(['invitation' => 'Invitation is already revoked.']);
            }
            $invitation->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('invitation.revoked', AuditEvent::ACTOR_OPERATOR, null, Invitation::class, $invitation->id);

            return $invitation;
        });
    }

    public function register(string $token, string $email, string $password): User
    {
        $normalizedEmail = $this->emails->validate($email);

        try {
            return DB::transaction(function () use ($token, $normalizedEmail, $password): User {
                $invitation = Invitation::query()->where('token_hash', $this->tokenHash($token))->lockForUpdate()->first();
                if ($invitation === null || ! $invitation->isUsable()) {
                    throw ValidationException::withMessages(['invitation_token' => 'This invitation is not available.']);
                }
                if ($invitation->target_email !== null && $invitation->target_email !== $normalizedEmail) {
                    throw ValidationException::withMessages(['invitation_token' => 'This invitation is not valid for this email address.']);
                }
                if (User::query()->where('email', $normalizedEmail)->exists()) {
                    throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
                }

                $user = new User;
                $user->forceFill([
                    'email' => $normalizedEmail,
                    'password' => Hash::make($password),
                    'role' => User::ROLE_USER,
                    'status' => User::STATUS_ACTIVE,
                ])->save();
                $invitation->forceFill(['uses' => 1, 'consumed_at' => now()])->save();
                $this->audit->record('invitation.consumed', AuditEvent::ACTOR_SYSTEM, null, Invitation::class, $invitation->id);
                $this->audit->record('user.registered', AuditEvent::ACTOR_SYSTEM, null, User::class, $user->id);

                return $user;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this email address.']);
            }

            throw $exception;
        }
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driver = DB::getDriverName();

        return ($driver === 'pgsql' && $sqlState === '23505')
            || ($driver === 'sqlite' && $sqlState === '23000')
            || ($driver === 'mysql' && $sqlState === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062);
    }
}
