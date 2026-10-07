<?php

namespace App\Models;

use App\Contracts\Moderatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['body'])]
class Message extends Model implements Moderatable
{
    use SoftDeletes;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'attachment_file_id');
    }

    public function removeByModerator(User $moderator, string $reason): void
    {
        $this->forceFill(['status' => 'removed'])->save();
    }

    public function moderationSummary(): string
    {
        return Str::limit($this->body, 300);
    }
}
