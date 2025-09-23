<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    use HasFactory, MultiTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFixed($query)
    {
        return $query->where('type', 'fixed');
    }

    public function scopeInvestment($query)
    {
        return $query->where('type', 'investment');
    }

    public function scopeLeasable($query)
    {
        return $query->where('type', 'leasable');
    }
}
