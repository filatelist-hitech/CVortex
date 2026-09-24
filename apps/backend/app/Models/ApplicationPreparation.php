<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ApplicationPreparation extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_APPROVED = 'APPROVED';

    protected $guarded = ['*'];
}
