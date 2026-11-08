<?php

namespace App\Models;

use App\Support\Resources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = ['resource_type', 'resource_id', 'resource_name', 'title', 'severity', 'status', 'trigger_status', 'detail', 'check_count', 'started_at', 'last_seen_at', 'resolved_at', 'resolution', 'resolved_by', 'resolution_note', 'source'];

    protected $casts = ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'check_count' => 'integer', 'resource_id' => 'integer'];

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeForResource(Builder $query, string $type, int $id): Builder
    {
        return $query->where('resource_type', $type)->where('resource_id', $id);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function durationSeconds(): int
    {
        return max(0, ($this->resolved_at ?? now())->getTimestamp() - $this->started_at->getTimestamp());
    }

    public function durationForHumans(): string
    {
        $seconds = $this->durationSeconds();
        if ($seconds < 60) return $seconds.'s';
        if ($seconds < 3600) return intdiv($seconds, 60).'m';
        if ($seconds < 86400) return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
        return intdiv($seconds, 86400).'d '.intdiv($seconds % 86400, 3600).'h';
    }

    public function resourceUrl(): ?string
    {
        if (! Resources::find($this->resource_type, $this->resource_id)) return null;
        return route('inventory.show', ['type' => $this->resource_type, 'id' => $this->resource_id]);
    }
}
