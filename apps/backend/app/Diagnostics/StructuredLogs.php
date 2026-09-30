<?php

namespace App\Diagnostics;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\LogRecord;
use Throwable;

final class StructuredLogs
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            $exception = $record->context['exception'] ?? null;
            $context = (array) Redactor::context($record->context);
            if ($exception instanceof Throwable) {
                $context['safe_stack'] = Redactor::stack($exception);
            }

            return $record->with(
                message: $exception instanceof Throwable ? $exception::class : Redactor::text($record->message),
                context: ['service' => 'backend', 'environment' => app()->environment(), ...$context],
            );
        });
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter);
            }
        }
    }
}
