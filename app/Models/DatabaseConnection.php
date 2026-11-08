<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseConnection extends Model
{
    protected $fillable = ['name', 'engine', 'version', 'host', 'port', 'environment', 'status', 'size_mb', 'backup_status', 'last_backup_at', 'application_id', 'notes'];
    protected $casts = ['port' => 'integer', 'size_mb' => 'decimal:2', 'last_backup_at' => 'datetime'];
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
}
