<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'key', 'description', 'status', 'priority', 'owner_id', 'start_date', 'due_date'];
    protected $casts = ['start_date' => 'date', 'due_date' => 'date'];
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function progress(): int
    {
        $total = $this->tasks()->count();
        return $total ? (int) round($this->tasks()->where('status', 'done')->count() / $total * 100) : 0;
    }
}
