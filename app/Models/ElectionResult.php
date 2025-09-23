<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class ElectionResult extends Model
{
    use MultiTenant;

    protected $fillable = [
        'election_id',
        'candidate_id',
        'choice',
        'total_votes',
        'percentage',
        'rank',
        'is_winner',
        'calculation_details',
        'tenant_id',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'is_winner' => 'boolean',
        'calculation_details' => 'array',
    ];

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function scopeWinners($query)
    {
        return $query->where('is_winner', true);
    }

    public function scopeOrderedByVotes($query)
    {
        return $query->orderBy('total_votes', 'desc');
    }
}
