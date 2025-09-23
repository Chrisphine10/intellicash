<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VotingPosition extends Model
{
    use MultiTenant;

    protected $fillable = [
        'name',
        'description',
        'max_winners',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function elections()
    {
        return $this->hasMany(Election::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
