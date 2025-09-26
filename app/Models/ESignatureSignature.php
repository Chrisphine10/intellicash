<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Services\ESignatureSecurityService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ESignatureSignature extends Model
{
    use MultiTenant;

    protected $table = 'esignature_signatures';

    protected $fillable = [
        'document_id',
        'tenant_id',
        'signer_email',
        'signer_name',
        'signer_phone',
        'signer_company',
        'signature_token',
        'status',
        'signature_data',
        'signature_type',
        'filled_fields',
        'signature_metadata',
        'ip_address',
        'user_agent',
        'browser_info',
        'device_info',
        'sent_at',
        'viewed_at',
        'signed_at',
        'expires_at',
    ];

    protected $casts = [
        'filled_fields' => 'array',
        'signature_metadata' => 'array',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(ESignatureDocument::class, 'document_id');
    }

    public function auditTrail(): HasMany
    {
        return $this->hasMany(ESignatureAuditTrail::class, 'signature_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSigned($query)
    {
        return $query->where('status', 'signed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    // Accessors & Mutators
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucfirst($value),
        );
    }

    protected function signatureType(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucfirst($value ?? 'Unknown'),
        );
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeSigned(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function getSignatureUrl(): string
    {
        return route('esignature.public.sign', ['token' => $this->signature_token]);
    }

    /**
     * Get signature image URL with decryption
     */
    public function getSignatureImageUrl(): ?string
    {
        $decryptedData = $this->decryptSignatureData();
        if ($decryptedData) {
            return 'data:image/png;base64,' . $decryptedData;
        }
        return null;
    }

    public function getBrowserInfo(): array
    {
        return [
            'browser' => $this->browser_info,
            'device' => $this->device_info,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
        ];
    }

    public function getSigningDuration(): ?int
    {
        if ($this->viewed_at && $this->signed_at) {
            return $this->signed_at->diffInMinutes($this->viewed_at);
        }
        return null;
    }

    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->update([
                'viewed_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'browser_info' => $this->parseBrowserInfo(request()->userAgent()),
                'device_info' => $this->parseDeviceInfo(request()->userAgent()),
            ]);
        }
    }

    public function markAsSigned(array $signatureData, string $signatureType = 'drawn'): void
    {
        $this->update([
            'status' => 'signed',
            'signature_data' => $signatureData['signature'] ?? null,
            'signature_type' => $signatureType,
            'filled_fields' => $signatureData['fields'] ?? [],
            'signature_metadata' => $signatureData['metadata'] ?? [],
            'signed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'browser_info' => $this->parseBrowserInfo(request()->userAgent()),
            'device_info' => $this->parseDeviceInfo(request()->userAgent()),
        ]);
    }

    public function markAsDeclined(string $reason = null): void
    {
        $this->update([
            'status' => 'declined',
            'signature_metadata' => array_merge($this->signature_metadata ?? [], [
                'decline_reason' => $reason,
                'declined_at' => now()->toISOString(),
            ]),
        ]);
    }

    private function parseBrowserInfo(string $userAgent): string
    {
        $browsers = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Edge' => 'Edge',
            'Opera' => 'Opera',
        ];

        foreach ($browsers as $browser => $name) {
            if (strpos($userAgent, $browser) !== false) {
                return $name;
            }
        }

        return 'Unknown Browser';
    }

    private function parseDeviceInfo(string $userAgent): string
    {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'Mobile Device';
        } elseif (preg_match('/Windows/', $userAgent)) {
            return 'Windows';
        } elseif (preg_match('/Mac/', $userAgent)) {
            return 'Mac';
        } elseif (preg_match('/Linux/', $userAgent)) {
            return 'Linux';
        }

        return 'Unknown Device';
    }

    /**
     * Generate secure signature token
     */
    public static function generateSecureToken(): string
    {
        $securityService = app(ESignatureSecurityService::class);
        return $securityService->generateSecureToken();
    }

    /**
     * Validate signature with cryptographic verification
     */
    public function validateSignature(string $signatureData, string $signatureType): bool
    {
        $securityService = app(ESignatureSecurityService::class);
        
        // Validate signature data format
        if (!$securityService->validateSignatureData($signatureData, $signatureType)) {
            return false;
        }

        // Check for suspicious activity
        $suspiciousActivity = $securityService->detectSuspiciousActivity(
            request()->ip(),
            request()->userAgent()
        );

        // If suspicious activity detected, require additional verification
        if (in_array(true, $suspiciousActivity)) {
            \Log::warning('Suspicious e-signature activity detected', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'signature_id' => $this->id,
                'patterns' => $suspiciousActivity
            ]);
            return false;
        }

        return true;
    }

    /**
     * Create cryptographic signature hash
     */
    public function createSignatureHash(string $signatureData): string
    {
        $securityService = app(ESignatureSecurityService::class);
        return $securityService->createSignatureHash(
            $signatureData,
            $this->signer_email,
            $this->document_id
        );
    }

    /**
     * Verify signature hash
     */
    public function verifySignatureHash(string $signatureData, string $providedHash): bool
    {
        $securityService = app(ESignatureSecurityService::class);
        return $securityService->verifySignatureHash(
            $signatureData,
            $this->signer_email,
            $this->document_id,
            $providedHash
        );
    }

    /**
     * Encrypt signature data before storage
     */
    public function encryptSignatureData(string $signatureData): string
    {
        $securityService = app(ESignatureSecurityService::class);
        return $securityService->encryptSignatureData($signatureData);
    }

    /**
     * Decrypt signature data for display
     */
    public function decryptSignatureData(): ?string
    {
        if (!$this->signature_data) {
            return null;
        }

        try {
            $securityService = app(ESignatureSecurityService::class);
            return $securityService->decryptSignatureData($this->signature_data);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt signature data', [
                'signature_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
