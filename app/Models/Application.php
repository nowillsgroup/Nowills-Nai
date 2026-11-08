<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'url', 'environment', 'status', 'owner_id', 'tech_stack', 'health_score', 'last_checked_at', 'notes'];
    protected $casts = ['tech_stack' => 'array', 'health_score' => 'integer', 'last_checked_at' => 'datetime'];
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function repository(): HasOne { return $this->hasOne(Repository::class); }
    public function databases(): HasMany { return $this->hasMany(DatabaseConnection::class); }
    public function infrastructure(): HasMany { return $this->hasMany(Infrastructure::class); }
}
