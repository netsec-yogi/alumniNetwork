<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Department extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }
}
