<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status, ticket and check-in fields are set by EventRegistrationService only. */
#[Fillable(['event_id', 'user_id'])]
class EventRegistration extends Model
{
    public const CONFIRMED = 'confirmed';

    public const WAITLISTED = 'waitlisted';

    public const CANCELLED = 'cancelled';

    protected $hidden = ['ticket_code'];

    protected function casts(): array
    {
        return [
            'guests' => 'integer',
            'checked_in_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function seats(): int
    {
        return 1 + $this->guests;
    }
}
