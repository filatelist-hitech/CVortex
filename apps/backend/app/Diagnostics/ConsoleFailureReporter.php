<?php

namespace App\Diagnostics;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ConsoleFailureReporter
{
    public function expectedMessage(Throwable $exception): ?string
    {
        if ($exception instanceof ValidationException) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    if (is_string($message) && trim($message) !== '') {
                        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? 'Please check the supplied values.', 0, 300);
                    }
                }
            }

            return 'Please check the supplied values.';
        }

        return $exception instanceof ModelNotFoundException ? 'The requested record was not found.' : null;
    }

    public function report(Throwable $exception, string $operation): string
    {
        $reference = 'cli_'.Str::ulid();
        $operation = preg_match('/\A[a-z][a-z0-9:_-]{0,80}\z/iD', $operation) === 1 ? $operation : 'artisan';

        try {
            app(IncidentRecorder::class)->record('CLI_COMMAND_FAILED', 'A console command failed.', 'console', 'ERROR', $exception, [
                'request_id' => $reference,
                'operation' => $operation,
            ]);
        } catch (Throwable) {
            // The command still reports failure if both diagnostic sinks are unavailable.
        }

        return $reference;
    }
}
