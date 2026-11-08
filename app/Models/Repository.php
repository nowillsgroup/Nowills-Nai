<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Repository extends Model
{
    protected $fillable = ['name', 'description', 'url', 'provider', 'default_branch', 'language', 'visibility', 'status', 'application_id', 'last_synced_at'];
    protected $casts = ['last_synced_at' => 'datetime'];
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
}
