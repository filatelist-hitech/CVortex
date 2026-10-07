<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $thread_id
 * @property string $vacancy_snapshot_id
 * @property string|null $run_id
 * @property string $career_signature
 * @property string $role
 * @property string $content
 * @property string $status
 */
class VacancyChatMessage extends Model
{
    use HasUlids;

    protected $table = 'vacancy_chat_messages';

    protected $guarded = ['id'];
}
