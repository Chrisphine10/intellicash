@extends('layouts.app')

@section('title', 'Business Loan Application')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-hand-holding-usd"></i> Business Loan Application</h4>
                    <p class="mb-0 mt-2">Apply for a business loan to grow your enterprise</p>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form id="loanApplicationForm" method="POST" action="{{ route('loan_application.store') }}" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- Loan Product Selection -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-clipboard-list"></i> Loan Product Selection</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="loan_product_id">Select Loan Product <span class="text-danger">*</span></label>
                                        <select name="loan_product_id" id="loan_product_id" class="form-control" required>
                                            <option value="">Choose a loan product...</option>
                                            @foreach($loanProducts as $product)
                                                <option value="{{ $product->id }}" 
                                                        data-min-amount="{{ $product->minimum_amount }}"
                                                        data-max-amount="{{ $product->maximum_amount }}"
                                                        data-interest-rate="{{ $product->interest_rate }}"
                                                        data-terms="{{ $product->term_min_months }}-{{ $product->term_max_months }}"
                                                        data-requires-collateral="{{ $product->requires_collateral }}"
                                                        data-requires-guarantor="{{ $product->requires_guarantor }}"
                                                        data-collateral-types="{{ json_encode($product->accepted_collateral_types) }}">
                                                    {{ $product->name }} - {{ $product->formatted_amount_range }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="requested_amount">Loan Amount (KES) <span class="text-danger">*</span></label>
                                        <input type="number" name="requested_amount" id="requested_amount" class="form-control" 
                                               placeholder="Enter loan amount" required min="1000">
                                        <small class="form-text text-muted" id="amount_range"></small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="loan_purpose">Purpose of Loan <span class="text-danger">*</span></label>
                                        <textarea name="loan_purpose" id="loan_purpose" class="form-control" rows="3" 
                                                  placeholder="Describe how you plan to use the loan funds" required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-store"></i> Business Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="business_name">Business Name <span class="text-danger">*</span></label>
                                        <input type="text" name="business_name" id="business_name" class="form-control" 
                                               placeholder="Enter business name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="business_type">Business Type <span class="text-danger">*</span></label>
                                        <select name="business_type" id="business_type" class="form-control" required>
                                            <option value="">Select business type...</option>
                                            <option value="retail">Retail</option>
                                            <option value="manufacturing">Manufacturing</option>
                                            <option value="service">Service</option>
                                            <option value="agriculture">Agriculture</option>
                                            <option value="technology">Technology</option>
                                            <option value="construction">Construction</option>
                                            <option value="hospitality">Hospitality</option>
                                            <option value="transport">Transport</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="business_registration_number">Business Registration Number</label>
                                        <input type="text" name="business_registration_number" id="business_registration_number" 
                                               class="form-control" placeholder="Enter registration number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="business_start_date">Business Start Date</label>
                                        <input type="date" name="business_start_date" id="business_start_date" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="number_of_employees">Number of Employees</label>
                                        <input type="number" name="number_of_employees" id="number_of_employees" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="monthly_revenue">Monthly Revenue (KES)</label>
                                        <input type="number" name="monthly_revenue" id="monthly_revenue" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="monthly_expenses">Monthly Expenses (KES)</label>
                                        <input type="number" name="monthly_expenses" id="monthly_expenses" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="business_description">Business Description <span class="text-danger">*</span></label>
                                        <textarea name="business_description" id="business_description" class="form-control" rows="4" 
                                                  placeholder="Describe your business, products/services, and target market" required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-user"></i> Personal Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_name">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="applicant_name" id="applicant_name" class="form-control" 
                                               placeholder="Enter your full name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_email">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" name="applicant_email" id="applicant_email" class="form-control" 
                                               placeholder="Enter your email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_phone">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" name="applicant_phone" id="applicant_phone" class="form-control" 
                                               placeholder="Enter your phone number" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_id_number">ID Number</label>
                                        <input type="text" name="applicant_id_number" id="applicant_id_number" class="form-control" 
                                               placeholder="Enter your ID number">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_dob">Date of Birth</label>
                                        <input type="date" name="applicant_dob" id="applicant_dob" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_marital_status">Marital Status <span class="text-danger">*</span></label>
                                        <select name="applicant_marital_status" id="applicant_marital_status" class="form-control" required>
                                            <option value="">Select marital status...</option>
                                            <option value="single">Single</option>
                                            <option value="married">Married</option>
                                            <option value="divorced">Divorced</option>
                                            <option value="widowed">Widowed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="applicant_dependents">Number of Dependents</label>
                                        <input type="number" name="applicant_dependents" id="applicant_dependents" class="form-control" 
                                               placeholder="0" min="0" value="0">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employment_status">Employment Status <span class="text-danger">*</span></label>
                                        <select name="employment_status" id="employment_status" class="form-control" required>
                                            <option value="">Select employment status...</option>
                                            <option value="self_employed">Self Employed</option>
                                            <option value="employed">Employed</option>
                                            <option value="unemployed">Unemployed</option>
                                            <option value="student">Student</option>
                                            <option value="retired">Retired</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row" id="employment_details" style="display: none;">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="employer_name">Employer Name</label>
                                        <input type="text" name="employer_name" id="employer_name" class="form-control" 
                                               placeholder="Enter employer name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="job_title">Job Title</label>
                                        <input type="text" name="job_title" id="job_title" class="form-control" 
                                               placeholder="Enter job title">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="employment_years">Years of Employment</label>
                                        <input type="number" name="employment_years" id="employment_years" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="monthly_income">Monthly Income (KES)</label>
                                        <input type="number" name="monthly_income" id="monthly_income" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="applicant_address">Address <span class="text-danger">*</span></label>
                                        <textarea name="applicant_address" id="applicant_address" class="form-control" rows="3" 
                                                  placeholder="Enter your full address" required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Collateral Information -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-shield-alt"></i> Collateral Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collateral_type">Collateral Type <span class="text-danger">*</span></label>
                                        <select name="collateral_type" id="collateral_type" class="form-control" required>
                                            <option value="">Select collateral type...</option>
                                            <option value="bank_statement">Bank Statement</option>
                                            <option value="payroll">Payroll</option>
                                            <option value="property">Property</option>
                                            <option value="vehicle">Vehicle</option>
                                            <option value="equipment">Equipment</option>
                                            <option value="inventory">Inventory</option>
                                            <option value="guarantor">Guarantor</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collateral_value">Collateral Value (KES)</label>
                                        <input type="number" name="collateral_value" id="collateral_value" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="collateral_description">Collateral Description</label>
                                        <textarea name="collateral_description" id="collateral_description" class="form-control" rows="3" 
                                                  placeholder="Describe the collateral in detail"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Guarantor Information -->
                        <div class="form-section" id="guarantor_section" style="display: none;">
                            <h5 class="section-title"><i class="fas fa-user-friends"></i> Guarantor Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guarantor_name">Guarantor Name</label>
                                        <input type="text" name="guarantor_name" id="guarantor_name" class="form-control" 
                                               placeholder="Enter guarantor's full name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guarantor_phone">Guarantor Phone</label>
                                        <input type="tel" name="guarantor_phone" id="guarantor_phone" class="form-control" 
                                               placeholder="Enter guarantor's phone number">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guarantor_email">Guarantor Email</label>
                                        <input type="email" name="guarantor_email" id="guarantor_email" class="form-control" 
                                               placeholder="Enter guarantor's email">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guarantor_relationship">Relationship</label>
                                        <input type="text" name="guarantor_relationship" id="guarantor_relationship" class="form-control" 
                                               placeholder="e.g., Friend, Family, Business Partner">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="guarantor_income">Guarantor Monthly Income (KES)</label>
                                        <input type="number" name="guarantor_income" id="guarantor_income" class="form-control" 
                                               placeholder="0" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Document Upload -->
                        <div class="form-section">
                            <h5 class="section-title"><i class="fas fa-file-upload"></i> Required Documents</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="business_documents">Business Documents</label>
                                        <input type="file" name="business_documents[]" id="business_documents" class="form-control" 
                                               multiple accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="form-text text-muted">Business registration, permits, etc. (PDF, JPG, PNG - Max 5MB each)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="financial_documents">Financial Documents</label>
                                        <input type="file" name="financial_documents[]" id="financial_documents" class="form-control" 
                                               multiple accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="form-text text-muted">Bank statements, financial statements (PDF, JPG, PNG - Max 5MB each)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="personal_documents">Personal Documents</label>
                                        <input type="file" name="personal_documents[]" id="personal_documents" class="form-control" 
                                               multiple accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="form-text text-muted">ID, proof of address, etc. (PDF, JPG, PNG - Max 5MB each)</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="collateral_documents">Collateral Documents</label>
                                        <input type="file" name="collateral_documents[]" id="collateral_documents" class="form-control" 
                                               multiple accept=".pdf,.jpg,.jpeg,.png">
                                        <small class="form-text text-muted">Property deeds, vehicle logbook, etc. (PDF, JPG, PNG - Max 5MB each)</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="form-section">
                            <div class="form-check">
                                <input type="checkbox" name="terms_accepted" id="terms_accepted" class="form-check-input" required>
                                <label class="form-check-label" for="terms_accepted">
                                    I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a> <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-section text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                            <p class="mt-3 text-muted">
                                Your application will be reviewed within 2-3 business days. 
                                You will receive an email confirmation with your application number.
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
}

.section-title {
    color: #007bff;
    border-bottom: 2px solid #007bff;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.form-group label {
    font-weight: 500;
    color: #495057;
}

.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.btn-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
    border: none;
    padding: 12px 30px;
    font-weight: 500;
}

.btn-primary:hover {
    background: linear-gradient(45deg, #0056b3, #004085);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
}

.card {
    border: none;
    border-radius: 15px;
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
    background: linear-gradient(45deg, #007bff, #0056b3) !important;
}

.alert {
    border-radius: 8px;
    border: none;
}

@media (max-width: 768px) {
    .form-section {
        padding: 1rem;
    }
    
    .section-title {
        font-size: 1.1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loanProductSelect = document.getElementById('loan_product_id');
    const requestedAmountInput = document.getElementById('requested_amount');
    const amountRangeSpan = document.getElementById('amount_range');
    const employmentStatusSelect = document.getElementById('employment_status');
    const employmentDetailsDiv = document.getElementById('employment_details');
    const guarantorSection = document.getElementById('guarantor_section');
    const collateralTypeSelect = document.getElementById('collateral_type');

    // Update amount range when loan product changes
    loanProductSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const minAmount = parseInt(selectedOption.dataset.minAmount);
            const maxAmount = parseInt(selectedOption.dataset.maxAmount);
            amountRangeSpan.textContent = `Amount range: KES ${minAmount.toLocaleString()} - KES ${maxAmount.toLocaleString()}`;
            
            requestedAmountInput.min = minAmount;
            requestedAmountInput.max = maxAmount;
        } else {
            amountRangeSpan.textContent = '';
        }
    });

    // Show/hide employment details
    employmentStatusSelect.addEventListener('change', function() {
        if (this.value === 'employed') {
            employmentDetailsDiv.style.display = 'block';
        } else {
            employmentDetailsDiv.style.display = 'none';
        }
    });

    // Show/hide guarantor section based on collateral type
    collateralTypeSelect.addEventListener('change', function() {
        if (this.value === 'guarantor') {
            guarantorSection.style.display = 'block';
        } else {
            guarantorSection.style.display = 'none';
        }
    });

    // Form validation
    const form = document.getElementById('loanApplicationForm');
    form.addEventListener('submit', function(e) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });

    // Remove invalid class on input
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
});
</script>
@endsection
