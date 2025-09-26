<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class SavingsProduct extends Model {
	use MultiTenant;
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'savings_products';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'name',
		'account_number_prefix',
		'starting_account_number',
		'currency_id',
		'bank_account_id',
		'interest_rate',
		'interest_method',
		'interest_period',
		'interest_posting_period',
		'min_bal_interest_rate',
		'allow_withdraw',
		'minimum_account_balance',
		'minimum_deposit_amount',
		'maintenance_fee',
		'maintenance_fee_posting_period',
		'auto_create',
		'status',
		'tenant_id',
	];

	/**
	 * Scope a query to only include active users.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @return void
	 */
	public function scopeActive($query) {
		$query->where('status', 1);
	}

	public function currency() {
		return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault();
	}

	public function accounts() {
		return $this->hasMany('App\Models\SavingsAccount', 'savings_product_id');
	}

	public function interestPosting() {
		return $this->hasMany('App\Models\InterestPosting', 'account_type_id');
	}

	public function maintenanceFee() {
		return $this->hasMany('App\Models\ScheduleTaskHistory', 'reference_id')->where('name', 'maintenance_fee');
	}

	public function bank_account() {
		return $this->belongsTo('App\Models\BankAccount', 'bank_account_id')->withDefault();
	}

}