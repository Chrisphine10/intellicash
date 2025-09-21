<?php

namespace App\Models;

use App\Traits\Member;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class FundsTransferRequest extends Model
{
    use MultiTenant, Member;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'funds_transfer_requests';

    protected $fillable = [
        'member_id',
        'debit_account_id',
        'transfer_type',
        'amount',
        'beneficiary_name',
        'beneficiary_account',
        'beneficiary_mobile',
        'beneficiary_bank_code',
        'description',
        'transaction_id',
        'status',
        'api_response'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'api_response' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    public function debitAccount()
    {
        return $this->belongsTo('App\Models\SavingsAccount', 'debit_account_id')->withDefault();
    }

    public function transaction()
    {
        return $this->belongsTo('App\Models\Transaction', 'transaction_id')->withDefault();
    }

    public function getStatusTextAttribute()
    {
        switch ($this->status) {
            case 0:
                return 'Pending';
            case 1:
                return 'Processing';
            case 2:
                return 'Completed';
            case 3:
                return 'Failed';
            default:
                return 'Unknown';
        }
    }

    public function getTransferTypeTextAttribute()
    {
        switch ($this->transfer_type) {
            case 'kcb_buni':
                return 'KCB Buni';
            case 'paystack_mpesa':
                return 'Paystack MPesa';
            case 'own_account':
                return 'Own Account Transfer';
            case 'other_account':
                return 'Other Account Transfer';
            default:
                return 'Unknown';
        }
    }
}
