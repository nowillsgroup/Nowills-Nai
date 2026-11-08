<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\DatabaseConnection;
use App\Models\Infrastructure;
use App\Models\Project;
use App\Models\Repository;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim($request->string('q')->toString());
        if (mb_strlen($q) < 2) return response()->json(['results' => []]);
        $like = '%'.$q.'%'; $results = [];
        foreach ([
            ['type' => 'Application', 'model' => Application::class, 'route' => 'inventory.show', 'params' => ['type' => 'applications']],
            ['type' => 'Repository', 'model' => Repository::class, 'route' => 'inventory.show', 'params' => ['type' => 'repositories']],
            ['type' => 'Database', 'model' => DatabaseConnection::class, 'route' => 'inventory.show', 'params' => ['type' => 'databases']],
            ['type' => 'Infrastructure', 'model' => Infrastructure::class, 'route' => 'inventory.show', 'params' => ['type' => 'infrastructure']],
            ['type' => 'Project', 'model' => Project::class, 'route' => 'projects.show', 'params' => []],
        ] as $def) {
            $items = $def['model']::where('name', 'like', $like)->limit(5)->get();
            foreach ($items as $item) {
                $params = array_merge($def['params'], $def['route'] === 'projects.show' ? ['project' => $item->id] : ['id' => $item->id]);
                $results[] = ['type' => $def['type'], 'title' => $item->name, 'subtitle' => $item->description ?? '', 'url' => route($def['route'], $params)];
            }
        }
        return response()->json(['results' => array_slice($results, 0, 20)]);
    }
}
