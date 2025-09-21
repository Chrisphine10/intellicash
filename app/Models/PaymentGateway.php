<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_gateways';


    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function scopeOnline($query) {
        return $query->where('type', 1);
    }

    public function scopeOffline($query) {
        return $query->where('type', 0);
    }

    public function getParametersAttribute($value) {
        return json_decode($value);
    }
}