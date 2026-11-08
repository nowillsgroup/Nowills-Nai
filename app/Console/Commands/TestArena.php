<?php

namespace App\Console\Commands;

use App\Services\ArenaGateway;
use Illuminate\Console\Command;

class TestArena extends Command
{
    protected $signature = 'arena:test';
    protected $description = 'Check the configured upstream NAi connection';

    public function handle(ArenaGateway $arena): int
    {
        $result = $arena->healthCheck();
        $this->line($result['message']);
        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
