<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['alumni_profile_id', 'category', 'award_year', 'citation', 'is_published'])]
class DistinguishedAlumnus extends Model
{
    protected $table = 'distinguished_alumni';

    public const CATEGORIES = [
        'industry' => 'Industry', 'research' => 'Research', 'entrepreneurship' => 'Entrepreneurship', 'public_service' => 'Public service',
        'academia' => 'Academia', 'technology' => 'Technology', 'social_impact' => 'Social impact',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'award_year' => 'integer'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class, 'alumni_profile_id');
    }
}
