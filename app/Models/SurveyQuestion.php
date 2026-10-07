<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['position', 'type', 'prompt', 'options', 'required'])]
class SurveyQuestion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean'];
    }
}
