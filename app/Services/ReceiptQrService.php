<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Exception;

class ReceiptQrService
{
    private $cryptoService;
    private $ethereumService;

    public function __construct(CryptographicProtectionService $cryptoService, EthereumService $ethereumService)
    {
        $this->cryptoService = $cryptoService;
        $this->ethereumService = $ethereumService;
    }

    /**
     * Generate QR code data for a transaction receipt
     */
    public function generateQrData(Transaction $transaction): array
    {
        try {
            // Check if QR code module is enabled for this tenant
            $tenant = app('tenant');
            if (!$tenant->isQrCodeEnabled()) {
                return [
                    'error' => 'QR Code module is not enabled for this tenant',
                    'enabled' => false
                ];
            }

            // Get tenant-specific QR code settings
            $qrCodeSettings = $tenant->qrCodeSettings;
            if (!$qrCodeSettings) {
                return [
                    'error' => 'QR Code settings not found for this tenant',
                    'enabled' => false
                ];
            }

            // Create unique transaction hash
            $transactionHash = $this->generateTransactionHash($transaction);
            
            // Generate verification token
            $verificationToken = $this->generateVerificationToken($transaction);
            
            // Store transaction hash on Ethereum (optional)
            $ethereumTxHash = null;
            if ($qrCodeSettings->ethereum_enabled) {
                $ethereumTxHash = $this->storeOnEthereum($transaction, $transactionHash);
            }
            
            // Ensure created_at is a Carbon instance for timestamp
            $createdAt = $transaction->created_at;
            if (is_string($createdAt)) {
                $createdAt = \Carbon\Carbon::parse($createdAt);
            }
            
            // Create QR data structure
            $qrData = [
                'tx_hash' => $transactionHash,
                'ethereum_tx' => $ethereumTxHash,
                'verification_url' => $this->getVerificationUrl($verificationToken),
                'timestamp' => $createdAt->timestamp,
                'tenant_id' => $transaction->tenant_id,
                'amount' => $transaction->amount,
                'currency' => $this->getCurrency($transaction),
                'type' => $transaction->type,
                'status' => $transaction->status,
                'verification_token' => $verificationToken
            ];

            // Store verification data in cache for quick access
            $this->storeVerificationData($verificationToken, $qrData);

            return $qrData;

        } catch (Exception $e) {
            Log::error('Failed to generate QR data', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Generate QR code image
     */
    public function generateQrCode(Transaction $transaction, int $size = null): string
    {
        $qrData = $this->generateQrData($transaction);
        
        // Check if QR code module is disabled
        if (isset($qrData['enabled']) && !$qrData['enabled']) {
            throw new Exception($qrData['error'] ?? 'QR Code module is not enabled');
        }
        
        $qrString = $this->encodeQrData($qrData);
        
        // Get QR code settings from tenant
        $tenant = app('tenant');
        $qrCodeSettings = $tenant->qrCodeSettings;
        
        if (!$size && $qrCodeSettings) {
            $size = $qrCodeSettings->qr_code_size ?? 200;
        }
        
        $errorCorrection = $qrCodeSettings->qr_code_error_correction ?? 'H';
        
        // Generate QR code as base64 image using SVG format (no Imagick required)
        $qrCode = QrCode::format('svg')
            ->size($size)
            ->margin(2)
            ->errorCorrection($errorCorrection)
            ->generate($qrString);

        return 'data:image/svg+xml;base64,' . base64_encode($qrCode);
    }

    /**
     * Verify transaction using QR code data
     */
    public function verifyTransaction(string $verificationToken): array
    {
        try {
            // Get verification data from cache
            $verificationData = $this->getVerificationData($verificationToken);
            
            if (!$verificationData) {
                return [
                    'valid' => false,
                    'error' => 'Invalid verification token'
                ];
            }

            // Verify transaction exists and is valid
            $transaction = Transaction::find($verificationData['transaction_id'] ?? null);
            
            if (!$transaction) {
                return [
                    'valid' => false,
                    'error' => 'Transaction not found'
                ];
            }

            // Verify transaction hash
            $currentHash = $this->generateTransactionHash($transaction);
            if ($currentHash !== $verificationData['tx_hash']) {
                return [
                    'valid' => false,
                    'error' => 'Transaction data has been modified'
                ];
            }

            // Verify Ethereum transaction if available
            if ($verificationData['ethereum_tx']) {
                $ethereumValid = $this->ethereumService->verifyTransaction($verificationData['ethereum_tx']);
                if (!$ethereumValid) {
                    return [
                        'valid' => false,
                        'error' => 'Ethereum verification failed'
                    ];
                }
            }

            return [
                'valid' => true,
                'transaction' => $this->formatTransactionData($transaction),
                'verification_data' => $verificationData
            ];

        } catch (Exception $e) {
            Log::error('Transaction verification failed', [
                'verification_token' => $verificationToken,
                'error' => $e->getMessage()
            ]);
            
            return [
                'valid' => false,
                'error' => 'Verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate unique transaction hash
     */
    private function generateTransactionHash(Transaction $transaction): string
    {
        // Ensure created_at is a Carbon instance
        $createdAt = $transaction->created_at;
        if (is_string($createdAt)) {
            $createdAt = \Carbon\Carbon::parse($createdAt);
        }
        
        $data = [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'type' => $transaction->type,
            'dr_cr' => $transaction->dr_cr,
            'member_id' => $transaction->member_id,
            'created_at' => $createdAt->toISOString(),
            'tenant_id' => $transaction->tenant_id,
            'status' => $transaction->status
        ];

        $dataString = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $dataString . config('app.key'));
    }

    /**
     * Generate verification token
     */
    private function generateVerificationToken(Transaction $transaction): string
    {
        // Ensure created_at is a Carbon instance
        $createdAt = $transaction->created_at;
        if (is_string($createdAt)) {
            $createdAt = \Carbon\Carbon::parse($createdAt);
        }
        
        $data = $transaction->id . $createdAt->timestamp . config('app.key');
        return hash('sha256', $data);
    }

    /**
     * Store transaction hash on Ethereum blockchain
     */
    private function storeOnEthereum(Transaction $transaction, string $transactionHash): ?string
    {
        try {
            // Only store on Ethereum if enabled and transaction is approved
            if (!config('ethereum.enabled', false) || $transaction->status !== 'approved') {
                return null;
            }

            return $this->ethereumService->storeTransactionHash($transaction, $transactionHash);
        } catch (Exception $e) {
            Log::warning('Failed to store on Ethereum', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Encode QR data as compact string
     */
    private function encodeQrData(array $qrData): string
    {
        // Create a compact JSON string
        $compactData = [
            'h' => $qrData['tx_hash'],
            'e' => $qrData['ethereum_tx'],
            't' => $qrData['timestamp'],
            'v' => $qrData['verification_token']
        ];

        return base64_encode(json_encode($compactData));
    }

    /**
     * Decode QR data from compact string
     */
    public function decodeQrData(string $qrString): array
    {
        $decoded = json_decode(base64_decode($qrString), true);
        
        return [
            'tx_hash' => $decoded['h'] ?? null,
            'ethereum_tx' => $decoded['e'] ?? null,
            'timestamp' => $decoded['t'] ?? null,
            'verification_token' => $decoded['v'] ?? null
        ];
    }

    /**
     * Get verification URL
     */
    private function getVerificationUrl(string $verificationToken): string
    {
        return route('public.receipt.verify', ['token' => $verificationToken]);
    }

    /**
     * Get currency from transaction
     */
    private function getCurrency(Transaction $transaction): string
    {
        if ($transaction->account) {
            return $transaction->account->savings_type->currency->name ?? 'USD';
        }
        
        if ($transaction->bankAccount) {
            return $transaction->bankAccount->currency->name ?? 'USD';
        }
        
        return 'USD';
    }

    /**
     * Store verification data in cache
     */
    private function storeVerificationData(string $verificationToken, array $qrData): void
    {
        $verificationData = [
            'transaction_id' => $qrData['transaction_id'] ?? null,
            'tx_hash' => $qrData['tx_hash'],
            'ethereum_tx' => $qrData['ethereum_tx'],
            'timestamp' => $qrData['timestamp'],
            'tenant_id' => $qrData['tenant_id'],
            'amount' => $qrData['amount'],
            'currency' => $qrData['currency'],
            'type' => $qrData['type'],
            'status' => $qrData['status']
        ];

        // Store for 30 days
        cache()->put("receipt_verify_{$verificationToken}", $verificationData, 30 * 24 * 60);
    }

    /**
     * Get verification data from cache
     */
    private function getVerificationData(string $verificationToken): ?array
    {
        return cache()->get("receipt_verify_{$verificationToken}");
    }

    /**
     * Format transaction data for verification response
     */
    private function formatTransactionData(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'type' => $transaction->type,
            'dr_cr' => $transaction->dr_cr,
            'status' => $transaction->status,
            'member_name' => $transaction->member->first_name . ' ' . $transaction->member->last_name,
            'account_number' => $transaction->account->account_number ?? $transaction->bankAccount->account_number ?? 'N/A',
            'created_at' => $transaction->created_at->toISOString(),
            'description' => $transaction->description
        ];
    }
}
