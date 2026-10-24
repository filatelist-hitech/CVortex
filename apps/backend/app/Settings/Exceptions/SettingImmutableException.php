<?php

namespace App\Settings\Exceptions;

use RuntimeException;

class SettingImmutableException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("Настройка [{$key}] заблокирована окружением и не может быть изменена через административный интерфейс.");
    }
}
