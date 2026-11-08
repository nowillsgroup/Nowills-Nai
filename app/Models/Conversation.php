<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Playbooks;
use App\Support\Subjects;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'title', 'subject_type', 'subject_id', 'playbook', 'arena_session_id', 'context', 'last_message_at'];
    protected $casts = ['context' => 'array', 'last_message_at' => 'datetime', 'subject_id' => 'integer'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->orderBy('id'); }

    public function subject(): ?EloquentModel
    {
        return $this->subject_type && $this->subject_id ? Subjects::find($this->subject_type, $this->subject_id) : null;
    }

    public function isScoped(): bool { return (bool) ($this->subject_type && $this->subject_id); }

    public function scopeLabel(): ?string
    {
        return $this->isScoped() ? Subjects::label($this->subject_type).' · '.($this->subject()?->name ?? 'deleted record') : null;
    }

    public function subjectUrl(): ?string
    {
        return $this->isScoped() ? Subjects::url($this->subject_type, $this->subject_id) : null;
    }

    public function playbookLabel(): ?string
    {
        return $this->playbook ? (Playbooks::get((string) $this->subject_type, $this->playbook)['label'] ?? $this->playbook) : null;
    }
}
