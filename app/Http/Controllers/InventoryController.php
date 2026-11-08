<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\User;
use App\Support\Audit;
use App\Support\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request, string $type)
    {
        $cfg = Inventory::type($type);
        $query = $cfg['model']::query();
        if (in_array('owner_id', array_keys($cfg['fields']))) $query->with('owner');
        if (in_array('application_id', array_keys($cfg['fields']))) $query->with('application');
        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($w) use ($q, $type) {
                $w->where('name', 'like', "%{$q}%");
                if (in_array($type, ['applications', 'repositories'])) $w->orWhere('description', 'like', "%{$q}%");
                if (in_array($type, ['databases', 'infrastructure'])) $w->orWhere('notes', 'like', "%{$q}%");
            });
        }
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('environment') && in_array('environment', array_keys($cfg['fields']))) $query->where('environment', $request->string('environment'));
        $items = $query->latest()->paginate(12)->withQueryString();
        return view('inventory.index', compact('type', 'cfg', 'items') + ['counts' => $this->counts($type)]);
    }

    public function create(string $type)
    {
        $this->authorizeWrite();
        $cfg = Inventory::type($type);
        return view('inventory.form', ['type' => $type, 'cfg' => $cfg, 'item' => new $cfg['model'](), 'mode' => 'create'] + $this->options());
    }

    public function store(Request $request, string $type)
    {
        $this->authorizeWrite();
        $cfg = Inventory::type($type);
        $data = $request->validate(Inventory::rules($type));
        $data = $this->prepare($type, $data);
        if ($type === 'applications') $data['slug'] = $this->uniqueSlug($data['name']);
        $item = $cfg['model']::create($data);
        Audit::log('inventory.created', 'Added '.strtolower($cfg['singular']).' “'.$item->name.'”', $item);
        return redirect()->route('inventory.index', $type)->with('success', $cfg['singular'].' added to the workspace.');
    }

    public function show(string $type, int $id)
    {
        $cfg = Inventory::type($type);
        $item = $cfg['model']::findOrFail($id);
        if (method_exists($item, 'owner')) $item->load('owner');
        if (method_exists($item, 'application')) $item->load('application');
        if (method_exists($item, 'repository')) $item->load(['repository', 'databases', 'infrastructure']);

        $checks = collect();
        $incidents = collect();
        if (in_array($type, \App\Support\Resources::MONITORED, true)) {
            $checks = \App\Models\ResourceCheck::for($type, $item->id)->latest('checked_at')->limit(12)->get();
            $incidents = \App\Models\Incident::open()->forResource($type, $item->id)->latest('started_at')->get();
        }

        return view('inventory.show', compact('type', 'cfg', 'item', 'checks', 'incidents'));
    }

    public function edit(string $type, int $id)
    {
        $this->authorizeWrite();
        $cfg = Inventory::type($type);
        $item = $cfg['model']::findOrFail($id);
        return view('inventory.form', ['type' => $type, 'cfg' => $cfg, 'item' => $item, 'mode' => 'edit'] + $this->options());
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->authorizeWrite();
        $cfg = Inventory::type($type);
        $item = $cfg['model']::findOrFail($id);
        $data = $request->validate(Inventory::rules($type));
        $item->update($this->prepare($type, $data));
        Audit::log('inventory.updated', 'Updated '.strtolower($cfg['singular']).' “'.$item->name.'”', $item);
        return redirect()->route('inventory.index', $type)->with('success', $cfg['singular'].' updated.');
    }

    public function destroy(string $type, int $id)
    {
        $this->authorizeWrite();
        $cfg = Inventory::type($type);
        $item = $cfg['model']::findOrFail($id);
        $name = $item->name;
        $item->delete();
        Audit::log('inventory.deleted', 'Removed '.strtolower($cfg['singular']).' “'.$name.'”');
        return redirect()->route('inventory.index', $type)->with('success', $cfg['singular'].' removed.');
    }

    private function prepare(string $type, array $data): array
    {
        if ($type === 'applications') {
            $data['tech_stack'] = filled($data['tech_stack'] ?? null)
                ? array_values(array_filter(array_map('trim', explode(',', $data['tech_stack'])))) : [];
        }
        return $data;
    }

    private function options(): array
    {
        return ['owners' => User::orderBy('name')->pluck('name', 'id'), 'applications' => Application::orderBy('name')->pluck('name', 'id')];
    }

    private function counts(string $type): array
    {
        $cfg = Inventory::type($type);
        return ['total' => $cfg['model']::count(), 'active' => $cfg['model']::whereIn('status', ['healthy', 'online', 'active'])->count()];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'application'; $slug = $base; $i = 2;
        while (Application::where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }

    private function authorizeWrite(): void
    {
        abort_unless(auth()->user()->canWrite(), 403);
    }
}
