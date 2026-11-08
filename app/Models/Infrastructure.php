<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Infrastructure extends Model
{
    protected $fillable = ['name', 'type', 'provider', 'region', 'hostname', 'ip_address', 'environment', 'status', 'specs', 'application_id', 'ssl_expires_at', 'notes'];
    protected $casts = ['ssl_expires_at' => 'date'];
    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
}
