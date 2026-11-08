<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'title'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function canWrite(): bool { return in_array($this->role, ['admin', 'operator'], true); }
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        return strtoupper(substr($parts[0] ?? 'N', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}
