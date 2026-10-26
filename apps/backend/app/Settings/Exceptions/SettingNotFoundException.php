<?php

namespace App\Settings\Exceptions;

use RuntimeException;

class SettingNotFoundException extends RuntimeException
{
    public function __construct(string $key)
    {
        parent::__construct("Неизвестная системная настройка [{$key}]. Создание произвольных настроек запрещено.");
    }
}
