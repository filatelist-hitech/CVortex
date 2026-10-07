<?php

namespace App\AI\ChatGpt;

use Illuminate\Support\Str;

final class HostIdentity
{
    public function get(): string
    {
        $path = (string) config('chatgpt.host_file');
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new PlanException('HOST_STORAGE_UNAVAILABLE', 503);
        }
        $file = fopen($path, 'c+');
        if ($file === false) {
            throw new PlanException('HOST_STORAGE_UNAVAILABLE', 503);
        }
        try {
            if (! flock($file, LOCK_EX)) {
                throw new PlanException('HOST_STORAGE_UNAVAILABLE', 503);
            }
            $id = trim((string) stream_get_contents($file));
            if ($id === '') {
                $id = 'urn:uuid:'.Str::uuid();
                if (fwrite($file, $id) !== strlen($id) || ! fflush($file)) {
                    throw new PlanException('HOST_STORAGE_UNAVAILABLE', 503);
                }
            }
            if (preg_match('/\Aurn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $id) !== 1) {
                throw new PlanException('HOST_ID_INVALID', 503);
            }
            chmod($path, 0600);

            return $id;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
