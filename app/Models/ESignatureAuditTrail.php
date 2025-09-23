<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ESignatureAuditTrail extends Model
{
    use MultiTenant;

    protected $table = 'esignature_audit_trail';

    protected $fillable = [
        'document_id',
        'signature_id',
        'tenant_id',
        'action',
        'actor_type',
        'actor_email',
        'actor_name',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'browser_info',
        'device_info',
        'location',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(ESignatureDocument::class, 'document_id');
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(ESignatureSignature::class, 'signature_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByActor($query, string $email)
    {
        return $query->where('actor_email', $email);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getActionDescription(): string
    {
        $descriptions = [
            'created' => 'Document created',
            'sent' => 'Document sent for signing',
            'viewed' => 'Document viewed by signer',
            'signed' => 'Document signed',
            'declined' => 'Document signing declined',
            'expired' => 'Document expired',
            'cancelled' => 'Document cancelled',
            'field_filled' => 'Field filled',
            'signature_added' => 'Signature added',
            'document_downloaded' => 'Document downloaded',
        ];

        return $descriptions[$this->action] ?? ucfirst($this->action);
    }

    public function getActorDisplayName(): string
    {
        if ($this->actor_name) {
            return $this->actor_name;
        }

        if ($this->actor_email) {
            return $this->actor_email;
        }

        return 'System';
    }

    public function getLocationInfo(): array
    {
        return [
            'ip_address' => $this->ip_address,
            'browser' => $this->browser_info,
            'device' => $this->device_info,
            'location' => $this->location,
        ];
    }

    public function getFormattedTimestamp(): string
    {
        return $this->created_at->format('M d, Y H:i:s');
    }

    public function getSecurityInfo(): array
    {
        return [
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'browser_info' => $this->browser_info,
            'device_info' => $this->device_info,
            'location' => $this->location,
            'timestamp' => $this->created_at->toISOString(),
        ];
    }

    // Static methods for creating audit entries
    public static function logDocumentCreated(ESignatureDocument $document, User $user): self
    {
        return self::create([
            'document_id' => $document->id,
            'tenant_id' => $document->tenant_id,
            'action' => 'created',
            'actor_type' => 'user',
            'actor_email' => $user->email,
            'actor_name' => $user->name,
            'description' => "Document '{$document->title}' created",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'browser_info' => self::parseBrowserInfo(request()->userAgent()),
            'device_info' => self::parseDeviceInfo(request()->userAgent()),
        ]);
    }

    public static function logDocumentSent(ESignatureDocument $document, User $user): self
    {
        return self::create([
            'document_id' => $document->id,
            'tenant_id' => $document->tenant_id,
            'action' => 'sent',
            'actor_type' => 'user',
            'actor_email' => $user->email,
            'actor_name' => $user->name,
            'description' => "Document '{$document->title}' sent for signing",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'browser_info' => self::parseBrowserInfo(request()->userAgent()),
            'device_info' => self::parseDeviceInfo(request()->userAgent()),
        ]);
    }

    public static function logDocumentViewed(ESignatureSignature $signature): self
    {
        return self::create([
            'document_id' => $signature->document_id,
            'signature_id' => $signature->id,
            'tenant_id' => $signature->tenant_id,
            'action' => 'viewed',
            'actor_type' => 'signer',
            'actor_email' => $signature->signer_email,
            'actor_name' => $signature->signer_name,
            'description' => "Document viewed by {$signature->signer_email}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'browser_info' => self::parseBrowserInfo(request()->userAgent()),
            'device_info' => self::parseDeviceInfo(request()->userAgent()),
        ]);
    }

    public static function logDocumentSigned(ESignatureSignature $signature): self
    {
        return self::create([
            'document_id' => $signature->document_id,
            'signature_id' => $signature->id,
            'tenant_id' => $signature->tenant_id,
            'action' => 'signed',
            'actor_type' => 'signer',
            'actor_email' => $signature->signer_email,
            'actor_name' => $signature->signer_name,
            'description' => "Document signed by {$signature->signer_email}",
            'metadata' => [
                'signature_type' => $signature->signature_type,
                'signing_duration' => $signature->getSigningDuration(),
                'fields_filled' => count($signature->filled_fields ?? []),
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'browser_info' => self::parseBrowserInfo(request()->userAgent()),
            'device_info' => self::parseDeviceInfo(request()->userAgent()),
        ]);
    }

    private static function parseBrowserInfo(string $userAgent): string
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

    private static function parseDeviceInfo(string $userAgent): string
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
}
