<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class DatabaseOwnerContext
{
    public function set(string $ownerId): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::selectOne("SELECT set_config('cvortex.owner_id', ?, false)", [$ownerId]);
    }

    public function clear(): void
    {
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
        if (DB::getDriverName() !== 'pgsql') {
            return $callback();
        }

        $previous = DB::scalar("SELECT current_setting('cvortex.owner_id', true)");
        $this->set($ownerId);

        try {
            return $callback();
        } finally {
            if (is_string($previous) && $previous !== '') {
                $this->set($previous);
            } else {
                $this->clear();
            }
        }
    }
}
