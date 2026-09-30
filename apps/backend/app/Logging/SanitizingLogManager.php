<?php

namespace App\Logging;

use App\Diagnostics\StructuredLogs;
use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

class SanitizingLogManager extends LogManager
{
    protected function createEmergencyLogger()
    {
        $config = $this->configurationFor('emergency') ?? [];
        $handler = new StreamHandler(
            $config['path'] ?? $this->app->storagePath().'/logs/laravel.log',
            Level::Debug,
        );
        $logger = new Logger(
            new MonologLogger('laravel', $this->prepareHandlers([$handler])),
            $this->app['events'],
        );

        (new StructuredLogs)($logger);

        return $logger;
    }
}
