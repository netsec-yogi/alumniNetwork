<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['status', 'method', 'matched_record_id', 'evidence_path', 'applicant_note'])]
class VerificationRequest extends Model
{
    public const METHOD_AUTO = 'auto_match';

    public const METHOD_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class, 'alumni_profile_id');
    }

    public function matchedRecord(): BelongsTo
    {
        return $this->belongsTo(AlumniRecord::class, 'matched_record_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
