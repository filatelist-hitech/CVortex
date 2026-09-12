<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class DependencyProbe
{
    public function ready(): bool
    {
        try {
            DB::select('select 1');
            Redis::connection()->ping();

            return true;
        } catch (Throwable) {
            Log::warning('A required readiness dependency is unavailable.');
            DB::purge();
            app()->forgetInstance('redis');

            return false;
        }
    }
}
