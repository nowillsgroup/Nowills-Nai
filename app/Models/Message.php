<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'status', 'arena_request_id', 'error', 'tokens'];
    protected $casts = ['tokens' => 'integer'];
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}
