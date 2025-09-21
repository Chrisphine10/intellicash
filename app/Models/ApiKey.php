<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use MultiTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'key',
        'secret',
        'type', // 'tenant', 'member'
        'permissions',
        'is_active',
        'last_used_at',
        'expires_at',
        'rate_limit',
        'ip_whitelist',
        'description',
        'created_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'ip_whitelist' => 'array',
    ];

    protected $hidden = [
        'secret',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($apiKey) {
            if (empty($apiKey->key)) {
                $apiKey->key = 'ic_' . Str::random(32);
            }
            if (empty($apiKey->secret)) {
                $apiKey->secret = Str::random(64);
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive()
    {
        return $this->is_active && !$this->isExpired();
    }

    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions ?? []);
    }

    public function canAccessIp($ip)
    {
        if (empty($this->ip_whitelist)) {
            return true;
        }

        return in_array($ip, $this->ip_whitelist);
    }

    public function updateLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    public function scopeTenantKeys($query)
    {
        return $query->where('type', 'tenant');
    }

    public function scopeMemberKeys($query)
    {
        return $query->where('type', 'member');
    }
}
