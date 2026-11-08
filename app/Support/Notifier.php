<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight in-app notifications. Deliberately separate from the audit
 * trail: activity records what happened for everyone; notifications target
 * the specific people who need to act.
 */
class Notifier
{
    public static function send(int|User $user, string $type, string $title, ?string $body = null, ?string $url = null, ?Model $subject = null): Notification
    {
        return Notification::create([
            'user_id' => $user instanceof User ? $user->id : $user,
            'type' => $type,
            'title' => mb_substr($title, 0, 200),
            'body' => $body !== null ? mb_substr($body, 0, 500) : null,
            'url' => $url !== null ? mb_substr($url, 0, 500) : null,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
        ]);
    }

    /** Fan out to every user holding one of the given roles, optionally skipping the actor. */
    public static function roles(array $roles, string $type, string $title, ?string $body = null, ?string $url = null, ?Model $subject = null, ?int $exceptUserId = null): int
    {
        $count = 0;
        foreach (User::whereIn('role', $roles)->get() as $user) {
            if ($exceptUserId !== null && $user->id === $exceptUserId) continue;
            self::send($user, $type, $title, $body, $url, $subject);
            $count++;
        }

        return $count;
    }
}
