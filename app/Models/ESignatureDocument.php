<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ESignatureDocument extends Model
{
    use MultiTenant;


    protected $table = 'esignature_documents';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'status',
        'custom_message',
        'sender_name',
        'sender_email',
        'sender_company',
        'signers',
        'fields',
        'signature_positions',
        'expires_at',
        'sent_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'signers' => 'array',
        'fields' => 'array',
        'signature_positions' => 'array',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function signatures(): HasMany
    {
        return $this->hasMany(ESignatureSignature::class, 'document_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ESignatureField::class, 'document_id');
    }

    public function auditTrail(): HasMany
    {
        return $this->hasMany(ESignatureAuditTrail::class, 'document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'sent']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'signed');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    // Accessors & Mutators
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucfirst($value),
        );
    }

    protected function documentType(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucfirst($value),
        );
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'signed';
    }

    public function canBeSigned(): bool
    {
        return $this->status === 'sent' && !$this->isExpired();
    }

    public function getSignerCount(): int
    {
        return count($this->signers ?? []);
    }

    public function getCompletedSignaturesCount(): int
    {
        return $this->signatures()->where('status', 'signed')->count();
    }

    public function isFullySigned(): bool
    {
        return $this->getSignerCount() > 0 && 
               $this->getCompletedSignaturesCount() === $this->getSignerCount();
    }

    public function getFileUrl(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function getFileExtension(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    public function getFormattedFileSize(): string
    {
        $bytes = (int) $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
