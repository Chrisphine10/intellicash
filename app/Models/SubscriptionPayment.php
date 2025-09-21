<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SubscriptionPayment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'subscription_payments';

    public function package() {
        return $this->belongsTo('App\Models\Package', 'package_id')->withDefault();
    }

    public function tenant() {
        return $this->belongsTo('App\Models\Tenant', 'tenant_id')->withDefault();
    }

    public function created_by() {
        return $this->belongsTo('App\Models\User', 'created_user_id')->withDefault();
    }

    protected function createdAt(): Attribute {
        $date_format = get_date_format();
        $time_format = get_time_format();

        return Attribute::make(
            get:fn($value) => \Carbon\Carbon::parse($value)->format("$date_format $time_format"),
        );
    }

    public function getExtraAttribute($value) {
        return json_decode($value);
    }
}