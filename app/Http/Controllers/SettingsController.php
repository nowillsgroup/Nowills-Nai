<?php

namespace App\Http\Controllers;

use App\Models\ArenaConnection;
use App\Models\Setting;
use App\Services\ArenaGateway;
use App\Support\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(ArenaGateway $arena)
    {
        return view('settings.index', ['workspace' => Setting::where('key', 'workspace_name')->value('value') ?? config('nai.workspace'), 'connection' => $arena->connection(), 'settings' => $arena->settings(), 'arenaStatus' => $arena->status()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'workspace_name' => 'required|string|max:120',
            'provider' => 'nullable|in:bridge,openai',
            'model' => ['nullable', 'string', 'max:120', 'regex:/\A[a-zA-Z0-9._-]+\z/'],
            'base_url' => 'nullable|url|max:500',
            'api_key' => 'nullable|string|max:1000',
            'clear_api_key' => 'nullable|boolean',
            'workspace_ref' => 'nullable|string|max:160',
            'chat_path' => 'required|string|max:200',
            'health_path' => 'required|string|max:200',
            'arena_portal_url' => 'nullable|url|max:500',
        ]);
        Setting::updateOrCreate(['key' => 'workspace_name'], ['value' => $data['workspace_name']]);
        $portal = \App\Support\ArenaPortal::sanitize($data['arena_portal_url'] ?? null);
        Setting::updateOrCreate(['key' => 'arena_portal_url'], ['value' => $portal]);
        $connection = ArenaConnection::query()->firstOrNew([]);
        $provider = $data['provider'] ?? ($connection->provider ?: 'bridge');
        $changedProvider = ($connection->provider ?: 'bridge') !== $provider;
        $connection->fill([
            'provider' => $provider,
            'model' => $data['model'] ?? $connection->model ?? ArenaGateway::DEFAULT_OPENAI_MODEL,
            'base_url' => $data['base_url'] ?? null,
            'workspace_ref' => $data['workspace_ref'] ?? null,
            'chat_path' => $data['chat_path'],
            'health_path' => $data['health_path'],
            'status' => 'disconnected',
            'last_error' => null,
        ]);
        if ($request->boolean('clear_api_key')) $connection->api_key = null;
        elseif (filled($data['api_key'] ?? null)) $connection->api_key = $data['api_key'];
        elseif ($changedProvider) $connection->api_key = null; // Never reuse a different provider's key.
        $connection->save();
        Audit::log('settings.updated', 'Updated workspace and NAi connection settings');
        return back()->with('success', 'Settings saved. Run a connection test to verify NAi.');
    }
}
