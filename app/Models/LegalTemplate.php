<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalTemplate extends Model
{
    protected $fillable = [
        'country_code',
        'country_name',
        'template_name',
        'template_type',
        'version',
        'terms_and_conditions',
        'privacy_policy',
        'description',
        'applicable_laws',
        'regulatory_bodies',
        'is_active',
        'is_system_template',
        'language_code',
    ];

    protected $casts = [
        'applicable_laws' => 'array',
        'regulatory_bodies' => 'array',
        'is_active' => 'boolean',
        'is_system_template' => 'boolean',
    ];

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for system templates
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_template', true);
    }

    /**
     * Scope for custom templates
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system_template', false);
    }

    /**
     * Scope for specific country
     */
    public function scopeForCountry($query, $countryCode)
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope for specific template type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('template_type', $type);
    }

    /**
     * Get templates by country and type
     */
    public static function getTemplatesForCountryAndType($countryCode, $type = null)
    {
        $query = static::active()->forCountry($countryCode);
        
        if ($type) {
            $query->ofType($type);
        }
        
        return $query->orderBy('template_name')->get();
    }

    /**
     * Get available countries
     */
    public static function getAvailableCountries()
    {
        return static::active()
            ->select('country_code', 'country_name')
            ->distinct()
            ->orderBy('country_name')
            ->get();
    }

    /**
     * Get template types for a country
     */
    public static function getTemplateTypesForCountry($countryCode)
    {
        return static::active()
            ->forCountry($countryCode)
            ->select('template_type')
            ->distinct()
            ->orderBy('template_type')
            ->pluck('template_type');
    }

    /**
     * Get formatted version
     */
    public function getFormattedVersionAttribute()
    {
        return "v{$this->version}";
    }

    /**
     * Get template display name
     */
    public function getDisplayNameAttribute()
    {
        return "{$this->template_name} ({$this->country_name})";
    }

    /**
     * Check if template is for specific country
     */
    public function isForCountry($countryCode)
    {
        return $this->country_code === $countryCode;
    }

    /**
     * Get applicable laws as formatted string
     */
    public function getApplicableLawsStringAttribute()
    {
        if (!$this->applicable_laws) {
            return 'Not specified';
        }
        
        return implode(', ', $this->applicable_laws);
    }

    /**
     * Get regulatory bodies as formatted string
     */
    public function getRegulatoryBodiesStringAttribute()
    {
        if (!$this->regulatory_bodies) {
            return 'Not specified';
        }
        
        return implode(', ', $this->regulatory_bodies);
    }
}
