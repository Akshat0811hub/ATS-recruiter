<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $guarded = ['id'];

    public const TYPES = [
        'call_candidate' => 'Call Candidate',
        'request_resume' => 'Request Resume',
        'screen_candidate' => 'Screen Candidate',
        'submit_profile' => 'Submit Profile',
        'request_client_feedback' => 'Request Client Feedback',
        'confirm_interview' => 'Confirm Interview',
        'collect_interview_results' => 'Collect Interview Results',
        'follow_up_on_offer' => 'Follow up on Offer',
        'confirm_joining' => 'Confirm Joining',
        'post_joining_follow_up' => 'Post-Joining Follow-up',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_recurring' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }
}
