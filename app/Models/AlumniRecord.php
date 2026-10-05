<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** The institute's authoritative graduate record, used for verification. */
#[Fillable(['roll_number', 'name', 'programme_id', 'admission_year', 'graduation_year', 'date_of_birth', 'email'])]
class AlumniRecord extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(AlumniProfile::class);
    }
}
