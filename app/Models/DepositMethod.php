<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class DepositMethod extends Model {
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_methods';

    public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault();
    }

    public function getRequirementsAttribute($value) {
        return json_decode($value);
    }

    public function chargeLimits() {
        return $this->morphMany(ChargeLimit::class, 'gateway');
    }
}