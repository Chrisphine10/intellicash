<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use MultiTenant;

    protected $fillable = [
        'election_id',
        'member_id',
        'candidate_id',
        'choice',
        'rank',
        'weight',
        'is_abstain',
        'voted_at',
        'tenant_id',
        'blockchain_hash',
        'encrypted_data',
        'is_verified',
        'verification_timestamp',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'latitude',
        'longitude',
        'digital_signature',
        'security_score',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
        'is_abstain' => 'boolean',
        'weight' => 'decimal:2',
        'is_verified' => 'boolean',
        'verification_timestamp' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'security_score' => 'integer',
    ];

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function scopeAbstain($query)
    {
        return $query->where('is_abstain', true);
    }

    public function scopeForCandidate($query, $candidateId)
    {
        return $query->where('candidate_id', $candidateId);
    }

    public function scopeRanked($query)
    {
        return $query->whereNotNull('rank');
    }
}
