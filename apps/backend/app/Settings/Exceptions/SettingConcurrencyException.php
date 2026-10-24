<?php

namespace App\Settings\Exceptions;

use RuntimeException;

class SettingConcurrencyException extends RuntimeException
{
    public function __construct(string $message = 'Настройка была изменена в другой сессии. Обновите страницу и повторите попытку.')
    {
        parent::__construct($message);
    }
}
