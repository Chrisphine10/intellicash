<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaRoleAssignment extends Model
{
    use MultiTenant;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'role',
        'assigned_at',
        'assigned_by',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeChairperson($query)
    {
        return $query->where('role', 'chairperson');
    }

    public function scopeTreasurer($query)
    {
        return $query->where('role', 'treasurer');
    }

    public function scopeSecretary($query)
    {
        return $query->where('role', 'secretary');
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayNameAttribute()
    {
        return ucfirst($this->role);
    }

    /**
     * Deactivate this role assignment
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }
}
