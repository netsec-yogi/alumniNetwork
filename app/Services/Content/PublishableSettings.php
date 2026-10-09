<?php

namespace App\Services\Content;

use App\Models\ContentRevision;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A site area that administrators edit as a draft and then publish
 * (landing-page text, portal branding). Visitors only ever see the
 * published version, or the defaults if nothing is published.
 *
 * Storage reuses site_settings: "{area}.draft" and "{area}.published" each
 * hold {values, by, at}. Every publish is also kept in content_revisions,
 * so an earlier version can be loaded back into the draft. Every change is
 * audited with the keys that changed and their before/after values.
 */
abstract class PublishableSettings
{
    public function __construct(protected readonly AuditLogger $audit) {}

    /** site_settings key prefix and content_revisions.area. */
    abstract public function area(): string;

    /** Audit action prefix, e.g. "branding" → "branding.published". */
    abstract protected function auditPrefix(): string;

    /** @return array<string, mixed> every key with its default */
    abstract public function defaults(): array;

    /**
     * Known keys only, each cleaned and bounded; missing keys get defaults.
     *
     * @return array<string, mixed>
     */
    abstract protected function normalize(array $values): array;

    /** Drop whatever caches the published version feeds. */
    abstract protected function flushPublished(): void;

    /** How a value appears in the audit log (override to summarise, e.g. files). */
    protected function auditValue(string $key, mixed $value): mixed
    {
        return is_string($value) ? Str::limit($value, 300) : $value;
    }

    /** @return array<string, mixed>|null */
    public function published(): ?array
    {
        $stored = SiteSetting::get($this->area().'.published');

        return is_array($stored['values'] ?? null) ? $this->normalize($stored['values']) : null;
    }

    /** What the editor shows: the draft, else the published version, else defaults. */
    public function draft(): array
    {
        $stored = SiteSetting::get($this->area().'.draft');

        return $this->normalize($stored['values'] ?? $this->published() ?? []);
    }

    /** The version visitors see (defaults when nothing is published). */
    public function live(): array
    {
        return $this->published() ?? $this->defaults();
    }

    /** @return array{is_published: bool, has_changes: bool, published_at: ?string, published_by: ?string, draft_at: ?string, draft_by: ?string} */
    public function status(): array
    {
        $draft = SiteSetting::get($this->area().'.draft');
        $published = SiteSetting::get($this->area().'.published');
        $names = User::whereIn('id', array_filter([$draft['by'] ?? null, $published['by'] ?? null]))->pluck('name', 'id');

        return [
            'is_published' => $published !== null,
            'has_changes' => $this->draft() != $this->live(),
            'published_at' => $published['at'] ?? null,
            'published_by' => $names[$published['by'] ?? 0] ?? null,
            'draft_at' => $draft['at'] ?? null,
            'draft_by' => $names[$draft['by'] ?? 0] ?? null,
        ];
    }

    /** @return array<string, array{0: mixed, 1: mixed}> changed keys → [before, after] */
    public function saveDraft(array $values, User $by): array
    {
        $before = $this->draft();
        $after = $this->normalize([...$before, ...$values]);
        $this->store('draft', $after, $by);

        $changes = $this->diff($before, $after);
        if ($changes !== []) {
            $this->record('draft_saved', $changes, $by);
        }

        return $changes;
    }

    public function publish(User $by): void
    {
        $before = $this->live();
        $values = $this->draft();

        DB::transaction(function () use ($values, $by) {
            $this->store('published', $values, $by);
            $revision = new ContentRevision;
            $revision->forceFill(['area' => $this->area(), 'values' => $values, 'published_by' => $by->id])->save();
        });
        $this->flushPublished();
        $this->record('published', $this->diff($before, $values), $by);
    }

    /** Visitors go back to the defaults; the draft is kept. */
    public function unpublish(User $by): void
    {
        $before = $this->live();
        SiteSetting::whereKey($this->area().'.published')->delete();
        $this->flushPublished();
        $this->record('unpublished', $this->diff($before, $this->defaults()), $by);
    }

    /** Load an earlier published version (or the defaults) into the draft. Publishing is a separate step. */
    public function restore(?ContentRevision $revision, User $by): void
    {
        abort_if($revision && $revision->area !== $this->area(), 404);
        $before = $this->draft();
        $values = $this->normalize($revision?->values ?? $this->defaults());
        $this->store('draft', $values, $by);
        $this->record('restored', $this->diff($before, $values), $by, $revision ? "Draft restored from version #{$revision->id}" : 'Draft reset to defaults');
    }

    /** @return list<array{id: int, at: string, by: ?string}> */
    public function history(int $limit = 15): array
    {
        return ContentRevision::where('area', $this->area())->with('publisher:id,name')->latest('id')->limit($limit)->get()
            ->map(fn (ContentRevision $r) => ['id' => $r->id, 'at' => $r->created_at->toIso8601String(), 'by' => $r->publisher?->name])
            ->all();
    }

    private function store(string $which, array $values, User $by): void
    {
        SiteSetting::put($this->area().'.'.$which, ['values' => $values, 'by' => $by->id, 'at' => now()->toIso8601String()]);
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    private function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changes[$key] = [$before[$key] ?? null, $value];
            }
        }

        return $changes;
    }

    private function record(string $action, array $changes, User $by, ?string $reason = null): void
    {
        $old = $new = [];
        foreach ($changes as $key => [$was, $now]) {
            $old[$key] = $this->auditValue($key, $was);
            $new[$key] = $this->auditValue($key, $now);
        }
        $this->audit->record($this->auditPrefix().'.'.$action, 'content', null, $old ?: null, $new ?: null, $by, 'success', $reason);
    }
}
