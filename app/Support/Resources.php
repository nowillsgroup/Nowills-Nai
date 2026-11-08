<?php

namespace App\Support;

use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use Illuminate\Database\Eloquent\Model;

/**
 * The subset of the inventory that NAi can actively probe.
 * Definitions are reused from Inventory so labels/icons never drift.
 */
class Resources
{
    public const MONITORED = ['applications', 'databases', 'infrastructure'];

    public static function types(): array
    {
        return array_intersect_key(Inventory::types(), array_flip(self::MONITORED));
    }

    public static function labels(): array
    {
        return array_map(fn ($type) => $type['singular'], self::types());
    }

    public static function model(string $type): string
    {
        return Inventory::type($type)['model'];
    }

    public static function icon(string $type): string
    {
        return Inventory::types()[$type]['icon'] ?? 'layers';
    }

    public static function label(string $type): string
    {
        return Inventory::types()[$type]['singular'] ?? ucfirst($type);
    }

    public static function find(string $type, int $id): ?Model
    {
        if (! in_array($type, self::MONITORED, true)) return null;
        return self::model($type)::find($id);
    }

    /** Human-readable probe target for a resource. */
    public static function target(Model $resource): ?string
    {
        return match (true) {
            $resource instanceof DatabaseConnection => $resource->host.($resource->port ? ':'.$resource->port : ''),
            $resource instanceof Infrastructure => $resource->hostname ?: $resource->ip_address,
            default => $resource->url ?? null,
        };
    }

    /** Normalise inventory statuses (online/healthy) into check statuses. */
    public static function normaliseStatus(string $status): string
    {
        return match ($status) {
            'online', 'healthy' => 'healthy',
            'degraded' => 'degraded',
            'offline', 'failed' => 'offline',
            default => 'unknown',
        };
    }
}
