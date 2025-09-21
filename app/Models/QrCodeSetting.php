<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCodeSetting extends Model
{
    use MultiTenant;

    protected $table = 'qr_code_settings';

    protected $fillable = [
        'tenant_id',
        'enabled',
        'ethereum_enabled',
        'ethereum_network',
        'ethereum_rpc_url',
        'ethereum_contract_address',
        'ethereum_account_address',
        'ethereum_private_key',
        'qr_code_size',
        'qr_code_error_correction',
        'verification_cache_days',
        'auto_generate_qr',
        'include_blockchain_verification',
        'custom_settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'ethereum_enabled' => 'boolean',
        'auto_generate_qr' => 'boolean',
        'include_blockchain_verification' => 'boolean',
        'custom_settings' => 'array',
    ];

    protected $hidden = [
        'ethereum_private_key',
    ];

    /**
     * Get the tenant that owns the QR code settings
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get available Ethereum networks
     */
    public static function getAvailableNetworks(): array
    {
        return [
            'mainnet' => [
                'name' => 'Ethereum Mainnet',
                'chain_id' => 1,
                'rpc_url' => 'https://mainnet.infura.io/v3/',
                'explorer' => 'https://etherscan.io',
            ],
            'goerli' => [
                'name' => 'Goerli Testnet',
                'chain_id' => 5,
                'rpc_url' => 'https://goerli.infura.io/v3/',
                'explorer' => 'https://goerli.etherscan.io',
            ],
            'sepolia' => [
                'name' => 'Sepolia Testnet',
                'chain_id' => 11155111,
                'rpc_url' => 'https://sepolia.infura.io/v3/',
                'explorer' => 'https://sepolia.etherscan.io',
            ],
            'polygon' => [
                'name' => 'Polygon Mainnet',
                'chain_id' => 137,
                'rpc_url' => 'https://polygon-mainnet.infura.io/v3/',
                'explorer' => 'https://polygonscan.com',
            ],
            'polygon_mumbai' => [
                'name' => 'Polygon Mumbai Testnet',
                'chain_id' => 80001,
                'rpc_url' => 'https://polygon-mumbai.infura.io/v3/',
                'explorer' => 'https://mumbai.polygonscan.com',
            ],
        ];
    }

    /**
     * Get network configuration
     */
    public function getNetworkConfig(): array
    {
        $networks = self::getAvailableNetworks();
        return $networks[$this->ethereum_network] ?? $networks['mainnet'];
    }

    /**
     * Check if QR code module is fully configured
     */
    public function isFullyConfigured(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->ethereum_enabled) {
            return !empty($this->ethereum_rpc_url) && 
                   !empty($this->ethereum_contract_address) && 
                   !empty($this->ethereum_account_address);
        }

        return true;
    }

    /**
     * Get QR code configuration for generation
     */
    public function getQrCodeConfig(): array
    {
        return [
            'size' => $this->qr_code_size,
            'error_correction' => $this->qr_code_error_correction,
            'margin' => 2,
        ];
    }

    /**
     * Get Ethereum configuration
     */
    public function getEthereumConfig(): array
    {
        if (!$this->ethereum_enabled) {
            return [];
        }

        return [
            'enabled' => true,
            'network' => $this->ethereum_network,
            'rpc_url' => $this->ethereum_rpc_url,
            'contract_address' => $this->ethereum_contract_address,
            'account_address' => $this->ethereum_account_address,
            'private_key' => $this->ethereum_private_key,
            'chain_id' => $this->getNetworkConfig()['chain_id'],
        ];
    }

    /**
     * Encrypt private key before saving
     */
    public function setEthereumPrivateKeyAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['ethereum_private_key'] = encrypt($value);
        }
    }

    /**
     * Decrypt private key when retrieving
     */
    public function getEthereumPrivateKeyAttribute($value)
    {
        if (!empty($value)) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        if (!$this->enabled) {
            return 'bg-secondary';
        }

        if ($this->isFullyConfigured()) {
            return 'bg-success';
        }

        return 'bg-warning';
    }

    /**
     * Get status text
     */
    public function getStatusText(): string
    {
        if (!$this->enabled) {
            return 'Disabled';
        }

        if ($this->isFullyConfigured()) {
            return 'Active';
        }

        return 'Needs Configuration';
    }
}
