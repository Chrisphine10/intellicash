<?php
namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use MultiTenant;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loans';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'loan_id',
        'loan_product_id',
        'borrower_id',
        'applied_amount',
        'total_payable',
        'total_paid',
        'currency_id',
        'first_payment_date',
        'release_date',
        'late_payment_penalties',
        'description',
        'status',
        'approved_date',
        'approved_user_id',
        'created_user_id',
        'branch_id',
        'comprehensive_data',
    ];

    protected static function booted()
    {
        static::addGlobalScope('borrower_id', function (Builder $builder) {
            if (auth()->check() && auth()->user()->user_type == 'user') {
                if (auth()->user()->all_branch_access == 1) {
                    if (session('branch_id') != '') {
                        $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                        return $builder->where('branch_id', $branch_id);
                    }
                } else {
                    return $builder->whereHas('borrower', function (Builder $query) {
                        $query->where('branch_id', auth()->user()->branch_id);
                    });
                }
            } else {
                if (session('branch_id') != '') {
                    $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                    return $builder->whereHas('borrower', function (Builder $query) use ($branch_id) {
                        $query->where('branch_id', $branch_id);
                    });
                }
            }
        });
    }

    public function borrower()
    {
        return $this->belongsTo('App\Models\Member', 'borrower_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault();
    }

    public function loan_product()
    {
        return $this->belongsTo('App\Models\LoanProduct', 'loan_product_id')->withDefault();
    }

    public function disburseTransaction()
    {
        return $this->hasOne('App\Models\Transaction', 'loan_id')
            ->where('type', 'Loan');
    }

    public function approved_by()
    {
        return $this->belongsTo('App\Models\User', 'approved_user_id')->withDefault();
    }

    public function created_by()
    {
        return $this->belongsTo('App\Models\User', 'created_user_id')->withDefault();
    }

    public function collaterals()
    {
        return $this->hasMany('App\Models\LoanCollateral', 'loan_id');
    }

    public function guarantors()
    {
        return $this->hasMany('App\Models\Guarantor', 'loan_id');
    }

    public function repayments()
    {
        return $this->hasMany('App\Models\LoanRepayment', 'loan_id');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\LoanPayment', 'loan_id');
    }

    public function next_payment()
    {
        return $this->hasOne('App\Models\LoanRepayment', 'loan_id')
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->withDefault();
    }

    public function getFirstPaymentDateAttribute($value)
    {
        $date_format = get_date_format();
        return \Carbon\Carbon::parse($value)->format("$date_format");
    }

    public function getReleaseDateAttribute($value)
    {
        if ($value != null) {
            $date_format = get_date_format();
            return \Carbon\Carbon::parse($value)->format("$date_format");
        }
    }

    public function getApprovedDateAttribute($value)
    {
        if ($value != null) {
            $date_format = get_date_format();
            return \Carbon\Carbon::parse($value)->format("$date_format");
        }
    }

    public function getCreatedAtAttribute($value)
    {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getUpdatedAtAttribute($value)
    {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

}
