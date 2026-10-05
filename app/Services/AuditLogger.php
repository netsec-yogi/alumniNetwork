<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the audit trail (SRS 80).
 *
 * Every entry carries the actor, request context and correlation id. Values
 * are passed through redact() first, so a caller that hands over a whole
 * request or model array cannot leak a password, OTP or TOTP secret.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        string $module,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        Authenticatable|int|null $actor = null,
    ): ?AuditLog {
        $actorId = match (true) {
            $actor instanceof Authenticatable => $actor->getAuthIdentifier(),
            is_int($actor) => $actor,
            default => $this->request->user()?->getAuthIdentifier(),
        };

        try {
            return AuditLog::create([
                'user_id' => $actorId,
                'action' => $action,
                'module' => $module,
                'entity_type' => $entity ? class_basename($entity) : null,
                'entity_id' => $entity?->getKey(),
                'old_values' => $old !== null ? $this->redact($old) : null,
                'new_values' => $new !== null ? $this->redact($new) : null,
                'ip_address' => $this->request->ip(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
                'request_id' => $this->request->attributes->get('request_id'),
            ]);
        } catch (Throwable $e) {
            // Losing an audit entry must be visible, but must not take the
            // user's action down with it.
            Log::channel('security')->error('Audit log write failed.', [
                'action' => $action,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Record a change to a model, logging only the attributes that changed. */
    public function recordChanges(string $action, string $module, Model $entity, array $original, ?User $actor = null): ?AuditLog
    {
        $changes = $entity->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return null;
        }

        return $this->record(
            $action,
            $module,
            $entity,
            array_intersect_key($original, $changes),
            $changes,
            $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redact(array $values): array
    {
        $sensitive = array_map('strtolower', config('security.redact'));

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
