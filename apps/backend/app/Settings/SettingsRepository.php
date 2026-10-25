<?php

namespace App\Settings;

use App\Models\SystemSetting;
use App\Settings\Exceptions\SettingConcurrencyException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SettingsRepository
{
    public function get(string $key): ?SystemSetting
    {
        return SystemSetting::query()->where('key', $key)->first();
    }

    /**
     * @return Collection<string, SystemSetting>
     */
    public function all(): Collection
    {
        return SystemSetting::query()->get()->keyBy('key');
    }

    public function save(string $key, mixed $value, bool $isSecret, ?string $userId = null, ?int $expectedVersion = null): SystemSetting
    {
        try {
            return DB::transaction(function () use ($key, $value, $isSecret, $userId, $expectedVersion): SystemSetting {
                /** @var SystemSetting|null $setting */
                $setting = SystemSetting::query()->where('key', $key)->lockForUpdate()->first();

                if ($setting !== null) {
                    if ($expectedVersion !== null && $setting->version !== $expectedVersion) {
                        throw new SettingConcurrencyException;
                    }

                    $setting->setDecodedValue($value, $isSecret);
                    $setting->updated_by = $userId;
                    $setting->version++;
                    $setting->save();

                    return $setting;
                }

                if ($expectedVersion !== null && $expectedVersion !== 1) {
                    throw new SettingConcurrencyException;
                }

                $newSetting = new SystemSetting([
                    'key' => $key,
                    'updated_by' => $userId,
                    'version' => 2,
                ]);
                $newSetting->setDecodedValue($value, $isSecret);
                $newSetting->save();

                return $newSetting;
            });
        } catch (UniqueConstraintViolationException) {
            throw new SettingConcurrencyException;
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'UNIQUE constraint failed') || str_contains($e->getMessage(), 'duplicate key')) {
                throw new SettingConcurrencyException;
            }
            throw $e;
        }
    }

    public function delete(string $key): bool
    {
        return (bool) SystemSetting::query()->where('key', $key)->delete();
    }
}
