<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'default_replacement_days' => 'integer',
            'storage_quota_bytes' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
