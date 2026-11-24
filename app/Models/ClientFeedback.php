<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFeedback extends Model
{
    protected $table = 'client_feedback';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'recorded_by_recruiter' => 'boolean',
        ];
    }

    public function clientSubmission(): BelongsTo
    {
        return $this->belongsTo(ClientSubmission::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
