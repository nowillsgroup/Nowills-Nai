<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'url', 'subject_type', 'subject_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime', 'subject_id' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->isUnread()) $this->update(['read_at' => now()]);
    }

    public function icon(): string
    {
        return match ($this->type) {
            'incident.opened' => 'alert',
            'incident.resolved' => 'check',
            'task.assigned', 'task.completed' => 'tag',
            default => 'bell',
        };
    }

    public function tone(): string
    {
        return match ($this->type) {
            'incident.opened' => 'red',
            'incident.resolved' => 'green',
            'task.assigned', 'task.completed' => 'blue',
            default => 'slate',
        };
    }

    /** Only same-origin relative paths may be used as redirect targets. */
    public function safeUrl(): ?string
    {
        $url = $this->url;
        if (! $url || ! str_starts_with($url, '/') || str_starts_with($url, '//')) return null;

        return $url;
    }
}
