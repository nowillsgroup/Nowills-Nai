<?php

namespace App\Models;

use App\Support\Resources;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResourceCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['resource_type', 'resource_id', 'resource_name', 'status', 'latency_ms', 'http_code', 'target', 'message', 'source', 'checked_at'];

    protected $casts = ['checked_at' => 'datetime', 'latency_ms' => 'integer', 'http_code' => 'integer', 'resource_id' => 'integer'];

    public function scopeFor(Builder $query, string $type, int $id): Builder
    {
        return $query->where('resource_type', $type)->where('resource_id', $id);
    }

    public function scopeSince(Builder $query, int $hours): Builder
    {
        return $query->where('checked_at', '>=', now()->subHours($hours));
    }

    public function resource(): ?Model
    {
        return Resources::find($this->resource_type, $this->resource_id);
    }

    public function isUp(): bool
    {
        return $this->status === 'healthy';
    }

    public function key(): string
    {
        return $this->resource_type.':'.$this->resource_id;
    }
}
