<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['project_id', 'title', 'description', 'status', 'priority', 'assignee_id', 'due_date', 'source_message_id'];
    protected $casts = ['due_date' => 'date'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function sourceMessage(): BelongsTo { return $this->belongsTo(Message::class, 'source_message_id'); }
}
