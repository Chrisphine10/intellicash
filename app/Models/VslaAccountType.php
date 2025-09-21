<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VslaAccountType extends Model
{
    use MultiTenant;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vsla_account_types';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active account types
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the VSLA transactions for this account type
     */
    public function vslaTransactions()
    {
        return $this->hasMany(VslaTransaction::class, 'account_type_id');
    }

    /**
     * Get the main VSLA bank account
     */
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Get formatted name with color
     */
    public function getFormattedNameAttribute()
    {
        return '<span style="color: ' . $this->color . ';">' . $this->name . '</span>';
    }
}
