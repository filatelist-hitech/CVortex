<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;

class AuditLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(string $eventType, string $actorType, ?User $actorUser = null, ?string $subjectType = null, ?string $subjectId = null, array $metadata = []): AuditEvent
    {
        /** @var AuditEvent $event */
        $event = AuditEvent::query()->create([
            'event_type' => $eventType,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUser?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $this->safeMetadata($metadata),
        ]);

        return $event;
    }

    /** @param array<string, mixed> $metadata
     *  @return array<string, mixed> */
    private function safeMetadata(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->safeMetadata($value) : $value;
        }

        return $safe;
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function safeLogContext(array $context): array
    {
        return array_filter(
            $context,
            fn (mixed $value, string|int $key): bool => ! $this->isSensitiveKey((string) $key),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/password|token|session|csrf|authorization|secret|api.?key/i', $key) === 1;
    }
}
