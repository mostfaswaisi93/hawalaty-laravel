<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * General platform-wide key/value settings - see BACKEND_PROMPT.md section 3.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    use HasUlids;
}
