<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserStatusService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function disable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->role === User::ROLE_ADMIN && User::query()->where('role', User::ROLE_ADMIN)->where('status', User::STATUS_ACTIVE)->count() === 1) {
                throw ValidationException::withMessages(['user' => 'The sole active admin cannot be disabled.']);
            }
            $user->forceFill(['status' => User::STATUS_DISABLED])->save();
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            $this->audit->record('user.disabled', AuditEvent::ACTOR_OPERATOR, null, User::class, $user->id);
        });
    }

    public function enable(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
            $this->audit->record('user.enabled', AuditEvent::ACTOR_OPERATOR, null, User::class, $user->id);
        });
    }
}
