<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvancedLoanApplication extends Model
{
    use HasFactory, MultiTenant;

    protected $fillable = [
        'tenant_id',
        'application_number',
        'loan_product_id',
        'applicant_id',
        'application_type',
        'application_date',
        'requested_amount',
        'approved_amount',
        'loan_purpose',
        'business_description',
        'business_type',
        'business_name',
        'business_registration_number',
        'business_start_date',
        'number_of_employees',
        'monthly_revenue',
        'monthly_expenses',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'applicant_address',
        'applicant_id_number',
        'applicant_dob',
        'applicant_marital_status',
        'applicant_dependents',
        'employment_status',
        'employer_name',
        'job_title',
        'monthly_income',
        'employment_years',
        'collateral_type',
        'collateral_description',
        'collateral_value',
        'collateral_documents',
        'guarantor_details',
        'business_documents',
        'financial_documents',
        'personal_documents',
        'status',
        'review_notes',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'credit_score',
        'risk_level',
        'risk_factors',
        'mitigation_measures',
        'additional_information',
        'custom_fields',
        'created_user_id',
        'updated_user_id'
    ];

    protected $casts = [
        'application_date' => 'date',
        'business_start_date' => 'date',
        'applicant_dob' => 'date',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'monthly_revenue' => 'decimal:2',
        'monthly_expenses' => 'decimal:2',
        'monthly_income' => 'decimal:2',
        'collateral_value' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'collateral_documents' => 'array',
        'guarantor_details' => 'array',
        'business_documents' => 'array',
        'financial_documents' => 'array',
        'personal_documents' => 'array',
        'custom_fields' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->application_number)) {
                $model->application_number = $model->generateApplicationNumber();
            }
        });
    }

    /**
     * Generate unique application number
     */
    public function generateApplicationNumber()
    {
        $prefix = 'ADV-';
        $year = date('Y');
        $month = date('m');
        
        $lastApplication = static::where('application_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('application_number', 'desc')
            ->first();
        
        if ($lastApplication) {
            $lastNumber = (int) substr($lastApplication->application_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the applicant (member)
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'applicant_id');
    }

    /**
     * Get the loan product
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    /**
     * Get the reviewer
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the approver
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    /**
     * Get the updater
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_user_id');
    }

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get loan if application is approved and loan is created
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'id', 'loan_application_id');
    }

    /**
     * Scope for different application types
     */
    public function scopeBusinessLoans($query)
    {
        return $query->where('application_type', 'business_loan');
    }

    public function scopeValueAdditionEnterprise($query)
    {
        return $query->where('application_type', 'value_addition_enterprise');
    }

    public function scopeStartupLoans($query)
    {
        return $query->where('application_type', 'startup_loan');
    }

    /**
     * Scope for different statuses
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'under_review']);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'disbursed']);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'disbursed' => 'Disbursed'
        ];

        return $labels[$this->status] ?? 'Unknown';
    }

    /**
     * Get application type label
     */
    public function getApplicationTypeLabelAttribute()
    {
        $labels = [
            'business_loan' => 'Business Loan',
            'value_addition_enterprise' => 'Value Addition Enterprise',
            'startup_loan' => 'Startup Loan'
        ];

        return $labels[$this->application_type] ?? 'Unknown';
    }

    /**
     * Get business type label
     */
    public function getBusinessTypeLabelAttribute()
    {
        $labels = [
            'retail' => 'Retail',
            'manufacturing' => 'Manufacturing',
            'service' => 'Service',
            'agriculture' => 'Agriculture',
            'technology' => 'Technology',
            'construction' => 'Construction',
            'hospitality' => 'Hospitality',
            'transport' => 'Transport',
            'other' => 'Other'
        ];

        return $labels[$this->business_type] ?? 'Unknown';
    }

    /**
     * Get collateral type label
     */
    public function getCollateralTypeLabelAttribute()
    {
        $labels = [
            'bank_statement' => 'Bank Statement',
            'payroll' => 'Payroll',
            'property' => 'Property',
            'vehicle' => 'Vehicle',
            'equipment' => 'Equipment',
            'inventory' => 'Inventory',
            'guarantor' => 'Guarantor'
        ];

        return $labels[$this->collateral_type] ?? 'Unknown';
    }

    /**
     * Get risk level label
     */
    public function getRiskLevelLabelAttribute()
    {
        $labels = [
            'low' => 'Low Risk',
            'medium' => 'Medium Risk',
            'high' => 'High Risk'
        ];

        return $labels[$this->risk_level] ?? 'Unknown';
    }

    /**
     * Check if application can be edited
     */
    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'submitted']);
    }

    /**
     * Check if application can be approved
     */
    public function canBeApproved()
    {
        return $this->status === 'under_review';
    }

    /**
     * Check if application can be rejected
     */
    public function canBeRejected()
    {
        return in_array($this->status, ['submitted', 'under_review']);
    }

    /**
     * Calculate debt to income ratio
     */
    public function getDebtToIncomeRatioAttribute()
    {
        if (!$this->monthly_income || $this->monthly_income <= 0) {
            return 0;
        }

        // Assuming monthly repayment is 10% of loan amount for simplicity
        $monthly_repayment = ($this->requested_amount * 0.1) / 12;
        return ($monthly_repayment / $this->monthly_income) * 100;
    }

    /**
     * Calculate loan to value ratio (if collateral provided)
     */
    public function getLoanToValueRatioAttribute()
    {
        if (!$this->collateral_value || $this->collateral_value <= 0) {
            return 0;
        }

        return ($this->requested_amount / $this->collateral_value) * 100;
    }
}
