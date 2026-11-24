<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AtsNotification extends Model
{
    use HasUuids;

    protected $table = 'notifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public static function sendToUser(int|User $user, string $title, string $message, string $category = 'general', ?string $actionUrl = null): self
    {
        $userId = $user instanceof User ? $user->id : $user;

        return static::create([
            'type' => 'App\\Notifications\\AtsSystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'data' => [
                'title' => $title,
                'message' => $message,
                'category' => $category,
                'action_url' => $actionUrl,
            ],
            'category' => $category,
            'action_url' => $actionUrl,
        ]);
    }
}
