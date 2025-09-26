<?php

namespace App\Models;

use App\Traits\Member;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class DepositRequest extends Model {

    use MultiTenant, Member;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'member_id',
        'deposit_method_id',
        'savings_account_id',
        'amount',
        'converted_amount',
        'charge',
        'description',
        'requirements',
        'attachment',
        'status',
        'transaction_id',
        'tenant_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'converted_amount' => 'decimal:2',
        'charge' => 'decimal:2',
        'requirements' => 'array',
        'status' => 'integer',
    ];

    /**
     * Get the deposit method that owns the deposit request.
     */
    public function method() {
        return $this->belongsTo('App\Models\DepositMethod', 'deposit_method_id')->withDefault();
    }

    /**
     * Get the member that owns the deposit request.
     */
    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    /**
     * Get the savings account that owns the deposit request.
     */
    public function account() {
        return $this->belongsTo('App\Models\SavingsAccount', 'savings_account_id')->withDefault();
    }

    /**
     * Get the transaction associated with the deposit request.
     */
    public function transaction() {
        return $this->belongsTo('App\Models\Transaction', 'transaction_id')->withDefault();
    }

    /**
     * Scope a query to only include pending deposit requests.
     */
    public function scopePending($query) {
        return $query->where('status', 0);
    }

    /**
     * Scope a query to only include approved deposit requests.
     */
    public function scopeApproved($query) {
        return $query->where('status', 2);
    }

    /**
     * Scope a query to only include rejected deposit requests.
     */
    public function scopeRejected($query) {
        return $query->where('status', 1);
    }

    /**
     * Get the status text attribute.
     */
    public function getStatusTextAttribute() {
        switch ($this->status) {
            case 0:
                return 'Pending';
            case 1:
                return 'Rejected';
            case 2:
                return 'Approved';
            default:
                return 'Unknown';
        }
    }
}