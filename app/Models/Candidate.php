<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use MultiTenant;

    protected $fillable = [
        'name',
        'bio',
        'manifesto',
        'photo',
        'order',
        'is_active',
        'election_id',
        'member_id',
        'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function results()
    {
        return $this->hasMany(ElectionResult::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function getVoteCountAttribute()
    {
        return $this->votes()->count();
    }

    public function getVotePercentageAttribute()
    {
        $totalVotes = $this->election->total_votes;
        return $totalVotes > 0 ? ($this->vote_count / $totalVotes) * 100 : 0;
    }
}
