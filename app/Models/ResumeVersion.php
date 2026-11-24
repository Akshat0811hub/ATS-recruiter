<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResumeVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function storageRecord(): BelongsTo
    {
        return $this->belongsTo(StorageRecord::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
