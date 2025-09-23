<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VotingAuditLog extends Model
{
    use MultiTenant;

    protected $fillable = [
        'election_id',
        'member_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'tenant_id',
        'performed_by',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForElection($query, $electionId)
    {
        return $query->where('election_id', $electionId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }
}
