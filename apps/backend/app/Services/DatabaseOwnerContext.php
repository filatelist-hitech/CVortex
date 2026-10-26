<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class DatabaseOwnerContext
{
    private static ?string $activeOwnerId = null;

    public static function currentOwnerId(): ?string
    {
        return self::$activeOwnerId;
    }

    public function set(string $ownerId): void
    {
        self::$activeOwnerId = $ownerId;

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne("SELECT set_config('cvortex.owner_id', ?, false)", [$ownerId]);
    }

    public function clear(): void
    {
        self::$activeOwnerId = null;

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne("SELECT set_config('cvortex.owner_id', '', false)");
    }

    /** @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(string $ownerId, Closure $callback): mixed
    {
        $previousMemory = self::$activeOwnerId;
        self::$activeOwnerId = $ownerId;

        if (DB::getDriverName() !== 'pgsql') {
            try {
                return $callback();
            } finally {
                self::$activeOwnerId = $previousMemory;
            }
        }

        $previous = DB::scalar("SELECT current_setting('cvortex.owner_id', true)");
        $this->set($ownerId);

        try {
            return $callback();
        } finally {
            self::$activeOwnerId = $previousMemory;
            if (is_string($previous) && $previous !== '') {
                $this->set($previous);
            } else {
                $this->clear();
            }
        }
    }
}
