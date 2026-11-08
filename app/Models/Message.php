<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'status', 'arena_request_id', 'error', 'tokens'];
    protected $casts = ['tokens' => 'integer'];
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function task(): HasOne { return $this->hasOne(Task::class, 'source_message_id'); }

    /** Only genuine, completed Arena AI replies can be converted into work. */
    public function isActionable(): bool
    {
        return $this->role === 'assistant' && $this->status === 'complete' && trim($this->content) !== '';
    }
}
