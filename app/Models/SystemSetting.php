<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $guarded = ['id'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::where('key', $key)->first();
        if (! $record) {
            return $default;
        }

        return match ($record->type) {
            'integer' => (int) $record->value,
            'boolean' => filter_var($record->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($record->value, true) ?? $default,
            default => $record->value,
        };
    }

    public static function setValue(string $key, mixed $value, string $group = 'general', string $type = 'string', ?int $userId = null): self
    {
        $encoded = $type === 'json' && is_array($value) ? json_encode($value) : (string) $value;

        return static::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $encoded,
                'type' => $type,
                'updated_by' => $userId,
            ]
        );
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
