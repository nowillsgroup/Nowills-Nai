<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Encrypted, private, short-lived reviews. Tokens never appear in URLs. */
class ImportReviewStore
{
    public const TTL_SECONDS = 900;
    private const DIRECTORY = 'import-reviews';

    public function __construct(private WorkspaceImporter $importer) {}

    public function put(array $plan, int $userId): string
    {
        $token = Str::random(64);
        $envelope = [
            'user_id' => $userId,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->timestamp,
            'plan_hash' => $this->importer->hash($plan),
            'plan' => $plan,
        ];
        $written = Storage::disk('local')->put($this->path($token), Crypt::encryptString(json_encode($envelope, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)));
        if ($written === false) throw new \RuntimeException('The private import review could not be stored. Check storage permissions and available disk space.');
        return $token;
    }

    public function peek(string $token, int $userId): ?array
    {
        if (!$this->validToken($token)) return null;
        try {
            $raw = Storage::disk('local')->get($this->path($token));
            if ($raw === null) return null;
            $envelope = json_decode(Crypt::decryptString($raw), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($envelope) || ($envelope['user_id'] ?? null) !== $userId || ($envelope['expires_at'] ?? 0) < now()->timestamp) return null;
            $plan = $envelope['plan'] ?? null;
            if (!is_array($plan) || ($envelope['plan_hash'] ?? null) !== $this->importer->hash($plan)) return null;
            return $plan;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Consume before applying. A failed or stale apply requires a new upload. */
    public function pull(string $token, int $userId): ?array
    {
        if (!$this->validToken($token)) return null;
        try {
            return Cache::lock('nai:import-review:'.hash('sha256', $token), 10)->block(3, function () use ($token, $userId) {
                $plan = $this->peek($token, $userId);
                if ($plan !== null) $this->forget($token);
                return $plan;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return null;
        }
    }

    public function forget(string $token): void
    {
        if ($this->validToken($token)) Storage::disk('local')->delete($this->path($token));
    }

    public function prune(): int
    {
        $count = 0;
        foreach (Storage::disk('local')->files(self::DIRECTORY) as $path) {
            if (!preg_match('/^import-reviews\/[a-f0-9]{64}\.json$/', $path)) continue;
            try {
                $raw = Storage::disk('local')->get($path);
                $envelope = $raw === null ? null : json_decode(Crypt::decryptString($raw), true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($envelope) || ($envelope['expires_at'] ?? 0) < now()->timestamp) { Storage::disk('local')->delete($path); $count++; }
            } catch (\Throwable) { Storage::disk('local')->delete($path); $count++; }
        }
        return $count;
    }

    private function validToken(string $token): bool { return preg_match('/^[A-Za-z0-9]{64}$/', $token) === 1; }
    private function path(string $token): string { return self::DIRECTORY.'/'.hash('sha256', $token).'.json'; }
}
