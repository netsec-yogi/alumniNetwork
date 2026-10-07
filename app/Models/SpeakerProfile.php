<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['is_available', 'topics', 'formats', 'bio', 'languages', 'remote', 'in_person'])]
class SpeakerProfile extends Model
{
    use HasFactory;

    public const FORMATS = ['talk' => 'Talk', 'workshop' => 'Workshop', 'panel' => 'Panel', 'fireside' => 'Fireside chat', 'webinar' => 'Webinar'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'topics' => 'array', 'formats' => 'array', 'remote' => 'boolean', 'in_person' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
