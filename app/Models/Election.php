<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    use MultiTenant;

    protected $fillable = [
        'title',
        'description',
        'type',
        'voting_mechanism',
        'privacy_mode',
        'allow_abstain',
        'weighted_voting',
        'start_date',
        'end_date',
        'status',
        'position_id',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'allow_abstain' => 'boolean',
        'weighted_voting' => 'boolean',
    ];

    public function position()
    {
        return $this->belongsTo(VotingPosition::class, 'position_id');
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function results()
    {
        return $this->hasMany(ElectionResult::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(VotingAuditLog::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               Carbon::now()->between($this->start_date, $this->end_date);
    }

    public function isExpired()
    {
        return Carbon::now()->isAfter($this->end_date);
    }

    public function canVote()
    {
        return $this->isActive() && $this->status === 'active';
    }

    public function getTotalVotesAttribute()
    {
        return $this->votes()->count();
    }

    public function getParticipationRateAttribute()
    {
        $totalMembers = Member::where('tenant_id', $this->tenant_id)->count();
        return $totalMembers > 0 ? ($this->total_votes / $totalMembers) * 100 : 0;
    }
}
