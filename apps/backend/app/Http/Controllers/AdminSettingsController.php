<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Settings\Exceptions\SettingConcurrencyException;
use App\Settings\Exceptions\SettingImmutableException;
use App\Settings\Exceptions\SettingNotFoundException;
use App\Settings\SettingsResolver;
use App\Settings\SettingTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminSettingsController extends Controller
{
    public function index(Request $request, SettingsResolver $resolver): JsonResponse
    {
        $this->requireAdmin($request);

        $settings = array_map(fn ($s) => $s->toArray(), $resolver->resolveAll());

        return response()->json([
            'data' => $settings,
            'meta' => [
                'total' => count($settings),
                'overrides_count' => count(array_filter($settings, fn ($s) => $s['is_overridden'])),
            ],
        ]);
    }

    public function update(Request $request, SettingsResolver $resolver): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->requireAdmin($request);

        $validated = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable'],
            'settings.*.expected_version' => ['nullable', 'integer'],
            'settings.*.clear_secret' => ['nullable', 'boolean'],
        ]);

        $updated = [];

        try {
            foreach ($validated['settings'] as $item) {
                $key = (string) $item['key'];
                $expectedVersion = isset($item['expected_version']) ? (int) $item['expected_version'] : null;

                if (! empty($item['clear_secret'])) {
                    $setting = $resolver->update($key, null, $admin, $expectedVersion);
                } else {
                    $setting = $resolver->update($key, $item['value'] ?? null, $admin, $expectedVersion);
                }

                $updated[] = $setting->toArray();
            }
        } catch (SettingConcurrencyException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SETTING_CONCURRENCY_CONFLICT',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        } catch (SettingImmutableException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SETTING_IMMUTABLE',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (SettingNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SETTING_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Ошибка проверки значения настройки.',
                    'errors' => $e->errors(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => $updated,
            'message' => 'Настройки успешно сохранены.',
        ]);
    }

    public function reset(Request $request, SettingsResolver $resolver): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->requireAdmin($request);

        $validated = $request->validate([
            'key' => ['required', 'string'],
        ]);

        try {
            $effective = $resolver->reset($validated['key'], $admin);
        } catch (SettingNotFoundException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SETTING_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        } catch (SettingImmutableException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SETTING_IMMUTABLE',
                    'message' => $e->getMessage(),
                ],
            ], 422);
        }

        return response()->json([
            'data' => $effective->toArray(),
            'message' => 'Настройка сброшена к исходному значению.',
        ]);
    }

    public function test(Request $request, SettingTestService $tester): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->requireAdmin($request);

        $validated = $request->validate([
            'target' => ['required', 'string', 'in:openai,smtp,storage'],
        ]);

        $result = $tester->test($validated['target'], $admin);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function environment(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $dbStatus = 'disconnected';
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (Throwable) {
            $dbStatus = 'error';
        }

        $redisStatus = 'disconnected';
        try {
            Redis::connection()->ping();
            $redisStatus = 'connected';
        } catch (Throwable) {
            $redisStatus = 'error';
        }

        $appKey = (string) config('app.key');

        return response()->json([
            'data' => [
                'app_name' => (string) config('app.name'),
                'app_env' => (string) config('app.env'),
                'app_debug' => (bool) config('app.debug'),
                'app_url' => (string) config('app.url'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => [
                    'default' => (string) config('database.default'),
                    'status' => $dbStatus,
                    'runtime_role' => (string) config('database.connections.pgsql.runtime_role', 'cvortex_app'),
                ],
                'redis' => [
                    'client' => (string) config('database.redis.client', 'phpredis'),
                    'status' => $redisStatus,
                ],
                'cache' => [
                    'default' => (string) config('cache.default'),
                ],
                'queue' => [
                    'default' => (string) config('queue.default'),
                ],
                'session' => [
                    'driver' => (string) config('session.driver'),
                    'secure' => (bool) config('session.secure'),
                ],
                'storage' => [
                    'default' => (string) config('filesystems.default'),
                ],
                'encryption' => [
                    'configured' => filled($appKey),
                    'cipher' => (string) config('app.cipher'),
                ],
                'logging' => [
                    'channel' => (string) config('logging.default'),
                    'level' => (string) config('logging.channels.stderr.level', config('logging.channels.single.level', 'debug')),
                ],
            ],
        ]);
    }

    private function requireAdmin(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user !== null && $user->role === User::ROLE_ADMIN, 403);

        return $user;
    }
}
