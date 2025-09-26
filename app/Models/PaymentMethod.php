<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethod extends Model
{
    use MultiTenant;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payment_methods';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'config',
        'is_active',
        'description',
        'currency_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Encrypt sensitive configuration data before saving
     */
    public function setConfigAttribute($value)
    {
        if (is_array($value)) {
            $value = $this->encryptSensitiveConfig($value);
        }
        $this->attributes['config'] = json_encode($value);
    }

    /**
     * Decrypt sensitive configuration data when retrieving
     */
    public function getConfigAttribute($value)
    {
        $config = json_decode($value, true) ?? [];
        return $this->decryptSensitiveConfig($config);
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault([
            'name' => 'KES',
            'full_name' => 'Kenyan Shilling'
        ]);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function depositRequests()
    {
        return $this->hasMany(DepositRequest::class);
    }

    public function withdrawRequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    /**
     * Scope for active payment methods
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific payment method type
     */
    public function scopeByType(Builder $query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for payment methods by currency
     */
    public function scopeByCurrency(Builder $query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    /**
     * Get configuration value
     */
    public function getConfig($key = null)
    {
        if ($key) {
            return $this->config[$key] ?? null;
        }
        return $this->config ?? [];
    }

    /**
     * Set configuration value
     */
    public function setConfig($config)
    {
        $this->config = $config;
        $this->save();
    }

    /**
     * Check if payment method is available for deposits
     */
    public function isAvailableForDeposits()
    {
        return $this->is_active && in_array($this->type, ['paystack', 'buni', 'manual']);
    }

    /**
     * Check if payment method is available for withdrawals
     */
    public function isAvailableForWithdrawals()
    {
        return $this->is_active && in_array($this->type, ['paystack', 'buni', 'manual']);
    }

    /**
     * Get display name with type
     */
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . ucfirst($this->type) . ')';
    }

    /**
     * Encrypt sensitive configuration fields
     */
    private function encryptSensitiveConfig(array $config)
    {
        $sensitiveFields = $this->getSensitiveFields();
        
        foreach ($sensitiveFields as $field) {
            if (isset($config[$field]) && !empty($config[$field])) {
                $config[$field] = encrypt($config[$field]);
            }
        }
        
        return $config;
    }

    /**
     * Decrypt sensitive configuration fields
     */
    private function decryptSensitiveConfig(array $config)
    {
        $sensitiveFields = $this->getSensitiveFields();
        
        foreach ($sensitiveFields as $field) {
            if (isset($config[$field]) && !empty($config[$field])) {
                try {
                    $config[$field] = decrypt($config[$field]);
                } catch (\Exception $e) {
                    // If decryption fails, keep the original value (might be unencrypted legacy data)
                    \Log::warning('Failed to decrypt payment method config field', [
                        'field' => $field,
                        'payment_method_id' => $this->id ?? 'new'
                    ]);
                }
            }
        }
        
        return $config;
    }

    /**
     * Get list of sensitive fields that should be encrypted
     */
    private function getSensitiveFields()
    {
        return [
            'paystack_secret_key',
            'paystack_public_key',
            'buni_client_secret',
            'buni_client_id',
            'api_key',
            'secret_key',
            'private_key',
            'password',
            'token'
        ];
    }
}
