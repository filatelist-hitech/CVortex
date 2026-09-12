<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EmailNormalizer;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, InvitationService $invitations): JsonResponse
    {
        $data = $request->validate([
            'invitation_token' => ['required', 'string', 'size:64'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'min:15', 'max:128', 'confirmed'],
        ]);
        $user = $invitations->register($data['invitation_token'], $data['email'], $data['password']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $this->identity($user)], 201);
    }

    public function login(Request $request, EmailNormalizer $emails, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:128'],
        ]);
        $email = $emails->normalize($data['email']);
        $user = User::query()->where('email', $email)->first();
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }
        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => 'This account is disabled.']);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        Log::info('auth.login.succeeded', $audit->safeLogContext(['user_id' => $user->id]));

        return response()->json(['data' => $this->identity($user)]);
    }

    public function logout(Request $request, AuditLogger $audit): JsonResponse
    {
        $userId = (string) $request->user()->id;
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Log::info('auth.logout.succeeded', $audit->safeLogContext(['user_id' => $userId]));

        return response()->json(status: 204);
    }

    /** @return array{id: string, email: string, role: string, status: string} */
    private function identity(User $user): array
    {
        return ['id' => $user->id, 'email' => $user->email, 'role' => $user->role, 'status' => $user->status];
    }
}
