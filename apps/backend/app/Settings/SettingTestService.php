<?php

namespace App\Settings;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SettingTestService
{
    public function __construct(
        private readonly SettingsResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{success: bool, message: string, latency_ms: int}
     */
    public function test(string $target, ?User $actor = null): array
    {
        $start = microtime(true);

        try {
            $result = match ($target) {
                'openai' => $this->testOpenAi(),
                'smtp' => $this->testSmtp(),
                'storage' => $this->testStorage(),
                default => throw new \InvalidArgumentException("Неизвестная цель проверки [{$target}]."),
            };

            $latency = (int) round((microtime(true) - $start) * 1000);

            $this->audit->record(
                eventType: 'setting.test_executed',
                actorType: $actor !== null ? AuditEvent::ACTOR_USER : AuditEvent::ACTOR_SYSTEM,
                actorUser: $actor,
                subjectType: null,
                subjectId: null,
                metadata: [
                    'target' => $target,
                    'success' => $result['success'],
                    'latency_ms' => $latency,
                ],
            );

            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);

            $this->audit->record(
                eventType: 'setting.test_executed',
                actorType: $actor !== null ? AuditEvent::ACTOR_USER : AuditEvent::ACTOR_SYSTEM,
                actorUser: $actor,
                subjectType: null,
                subjectId: null,
                metadata: [
                    'target' => $target,
                    'success' => false,
                    'latency_ms' => $latency,
                ],
            );

            return [
                'success' => false,
                'message' => 'Ошибка проверки соединения: '.$e->getMessage(),
                'latency_ms' => $latency,
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function testOpenAi(): array
    {
        $apiKey = $this->resolver->get('ai.providers.openai.api_key');
        $baseUrl = (string) $this->resolver->get('ai.providers.openai.base_url', 'https://api.openai.com/v1');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'API ключ OpenAI не настроен.',
            ];
        }

        $url = rtrim($baseUrl, '/').'/models';

        $response = Http::withToken((string) $apiKey)
            ->timeout(5)
            ->get($url);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => 'Соединение с OpenAI успешно установлено (HTTP '.$response->status().').',
            ];
        }

        if ($response->status() === 401) {
            return [
                'success' => false,
                'message' => 'Неверный API ключ OpenAI (401 Unauthorized).',
            ];
        }

        return [
            'success' => false,
            'message' => 'OpenAI API вернул статус '.$response->status().'.',
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function testSmtp(): array
    {
        $host = (string) $this->resolver->get('mail.smtp.host', '127.0.0.1');
        $port = (int) $this->resolver->get('mail.smtp.port', 2525);

        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 4);

        if (! $fp) {
            return [
                'success' => false,
                'message' => "Не удалось подключиться к SMTP {$host}:{$port} ({$errstr}).",
            ];
        }

        $greeting = fgets($fp, 512);
        fclose($fp);

        return [
            'success' => true,
            'message' => "SMTP порт открыт и отвечает на {$host}:{$port}. ".trim((string) $greeting),
        ];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function testStorage(): array
    {
        $disk = Storage::disk('local');
        $testFile = '.health-check-'.bin2hex(random_bytes(8));
        $disk->put($testFile, 'ok');
        $read = $disk->get($testFile);
        $disk->delete($testFile);

        if ($read === 'ok') {
            return [
                'success' => true,
                'message' => 'Локальное дисковое хранилище доступно для чтения и записи.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Сбой проверки целостности файлового хранилища.',
        ];
    }
}
