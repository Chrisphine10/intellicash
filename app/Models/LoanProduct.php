<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model {
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loan_products';

    protected $fillable = [
        'tenant_id',
        'name',
        'loan_id_prefix',
        'starting_loan_id',
        'minimum_amount',
        'maximum_amount',
        'late_payment_penalties',
        'description',
        'interest_rate',
        'interest_type',
        'term',
        'term_period',
        'status',
        'loan_application_fee',
        'loan_application_fee_type',
        'loan_processing_fee',
        'loan_processing_fee_type',
        'loan_insurance_fee',
        'loan_insurance_fee_type',
    ];


    /**
     * Scope a query to only include active users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where('status', 1);
    }

    public function loans()
    {
        return $this->hasMany('App\Models\Loan', 'loan_product_id');
    }

}