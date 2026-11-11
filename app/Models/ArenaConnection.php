<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArenaConnection extends Model
{
    protected $fillable = ['provider', 'model', 'base_url', 'api_key', 'workspace_ref', 'chat_path', 'health_path', 'status', 'last_checked_at', 'last_error'];
    protected $casts = ['api_key' => 'encrypted', 'last_checked_at' => 'datetime'];
    protected $hidden = ['api_key'];
}
