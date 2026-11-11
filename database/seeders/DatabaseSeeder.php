<?php

namespace Database\Seeders;

use App\Models\ArenaConnection;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['key' => 'workspace_name'], ['value' => config('nai.workspace')]);
        ArenaConnection::firstOrCreate([], ['provider' => 'openai', 'model' => \App\Services\ArenaGateway::DEFAULT_OPENAI_MODEL, 'chat_path' => config('nai.arena.chat_path'), 'health_path' => config('nai.arena.health_path'), 'status' => 'disconnected']);
        if (config('nai.demo_mode')) $this->call(DemoSeeder::class);
    }
}
