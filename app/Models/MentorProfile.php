<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['is_accepting', 'categories', 'expertise', 'bio', 'preferred_mentee', 'max_mentees', 'availability', 'preferred_mode'])]
class MentorProfile extends Model
{
    use HasFactory;

    public const MENTEE_TYPES = ['students' => 'Students', 'alumni' => 'Alumni', 'both' => 'Students and alumni'];

    protected function casts(): array
    {
        return [
            'is_accepting' => 'boolean',
            'categories' => 'array',
            'expertise' => 'array',
            'max_mentees' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
