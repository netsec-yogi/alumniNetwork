<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only audit trail. Write through App\Services\AuditLogger. */
#[Fillable([
    'user_id', 'action', 'module', 'entity_type', 'entity_id',
    'old_values', 'new_values', 'status', 'reason', 'ip_address', 'user_agent', 'request_id',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Also enforced by database triggers (UPDATE refused; DELETE only past retention).
        // `audit:prune` deletes with a query, which deliberately bypasses this model guard.
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
