<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class LoanCollateral extends Model {
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loan_collaterals';

    public function loan() {
        return $this->belongsTo('App\Models\Loan', 'loan_id')->withDefault();
    }
}