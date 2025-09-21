<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class AutomaticGateway extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'automatic_gateways';

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function getParametersAttribute($value) {
        return json_decode($value);
    }

    public function getSupportedCurrenciesAttribute($value) {
        return json_decode($value);
    }
    
    public function chargeLimits() {
        return $this->morphMany(ChargeLimit::class, 'gateway');
    }

    public function tenantGateway() {
        return $this->hasOne(AutomaticGateway::class, 'slug', 'slug')->whereNotNull('tenant_id');
    }
}