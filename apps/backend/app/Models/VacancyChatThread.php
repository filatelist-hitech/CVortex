<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $status
 * @property string $vacancy_id
 * @property string|null $connection_id
 * @property string $provider
 * @property string|null $model
 */
class VacancyChatThread extends Model
{
    use HasUlids;

    protected $table = 'vacancy_chat_threads';

    protected $guarded = ['id'];
}
