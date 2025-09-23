<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ESignatureField extends Model
{
    use MultiTenant;

    protected $table = 'esignature_fields';

    protected $fillable = [
        'document_id',
        'tenant_id',
        'field_type',
        'field_name',
        'field_label',
        'field_value',
        'field_options',
        'is_required',
        'is_readonly',
        'position_x',
        'position_y',
        'width',
        'height',
        'page_number',
        'assigned_to',
    ];

    protected $casts = [
        'field_options' => 'array',
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'page_number' => 'integer',
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(ESignatureDocument::class, 'document_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeByPage($query, int $pageNumber)
    {
        return $query->where('page_number', $pageNumber);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('field_type', $type);
    }

    public function scopeAssignedTo($query, string $email)
    {
        return $query->where('assigned_to', $email);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    // Helper methods
    public function isSignatureField(): bool
    {
        return $this->field_type === 'signature';
    }

    public function isTextField(): bool
    {
        return in_array($this->field_type, ['text', 'textarea', 'email', 'phone', 'date']);
    }

    public function isChoiceField(): bool
    {
        return in_array($this->field_type, ['checkbox', 'radio', 'dropdown']);
    }

    public function getFieldOptions(): array
    {
        return $this->field_options ?? [];
    }

    public function hasOptions(): bool
    {
        return !empty($this->field_options);
    }

    public function getPosition(): array
    {
        return [
            'x' => $this->position_x ?? 0,
            'y' => $this->position_y ?? 0,
            'width' => $this->width ?? 200,
            'height' => $this->height ?? 50,
        ];
    }

    public function setPosition(int $x, int $y, int $width = 200, int $height = 50): void
    {
        $this->update([
            'position_x' => $x,
            'position_y' => $y,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function validateValue($value): bool
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        switch ($this->field_type) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'phone':
                return preg_match('/^[\+]?[1-9][\d]{0,15}$/', $value);
            case 'date':
                return strtotime($value) !== false;
            case 'checkbox':
                return is_bool($value);
            case 'radio':
            case 'dropdown':
                return in_array($value, $this->getFieldOptions());
            default:
                return true;
        }
    }

    public function getFormattedValue(): string
    {
        if (empty($this->field_value)) {
            return '';
        }

        switch ($this->field_type) {
            case 'date':
                return date('M d, Y', strtotime($this->field_value));
            case 'checkbox':
                return $this->field_value ? 'Yes' : 'No';
            case 'textarea':
                return nl2br($this->field_value);
            default:
                return $this->field_value;
        }
    }
}
