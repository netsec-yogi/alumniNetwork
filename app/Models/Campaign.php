<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['name', 'subject', 'body', 'channels', 'audience', 'scheduled_at'])]
class Campaign extends Model
{
    protected function casts(): array
    {
        return ['channels' => 'array', 'audience' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    /** Same safe Markdown rendering as stories: raw HTML stripped, unsafe links refused. */
    public function bodyHtml(): string
    {
        return Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
