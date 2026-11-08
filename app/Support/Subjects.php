<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * Records an NAi conversation can be scoped to. Scoping only changes the
 * context NAi sends upstream; it never generates content locally.
 */
class Subjects
{
    public static function types(): array
    {
        $types = [];
        foreach (Inventory::types() as $key => $cfg) {
            $types[$key] = ['model' => $cfg['model'], 'label' => $cfg['singular'], 'icon' => $cfg['icon']];
        }
        $types['projects'] = ['model' => Project::class, 'label' => 'Project', 'icon' => 'folder'];

        return $types;
    }

    public static function isValid(?string $type): bool
    {
        return $type !== null && isset(self::types()[$type]);
    }

    public static function find(string $type, int $id): ?Model
    {
        $types = self::types();
        if (! isset($types[$type])) return null;

        return $types[$type]['model']::find($id);
    }

    public static function label(string $type): string
    {
        return self::types()[$type]['label'] ?? ucfirst($type);
    }

    public static function icon(string $type): string
    {
        return self::types()[$type]['icon'] ?? 'layers';
    }

    public static function url(string $type, int $id): ?string
    {
        if ($type === 'projects') return Project::find($id) ? route('projects.show', $id) : null;
        if (! isset(Inventory::types()[$type])) return null;

        return route('inventory.show', ['type' => $type, 'id' => $id]);
    }
}
