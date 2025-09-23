@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Apply New Loan') }}</span>
				<p class="mb-0 mt-2 text-muted">Complete the loan application form below</p>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('loans.apply_loan') }}" enctype="multipart/form-data" id="loanForm">
					@csrf
					
					<!-- Basic Loan Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-clipboard-list"></i> Loan Information</h5>
						<div class="row">

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Loan Product') }}</label>
									<select class="form-control auto-select select2" data-selected="{{ request()->product ?? old('loan_product_id') }}" name="loan_product_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\LoanProduct::active()->get() as $loanProduct)
										<option value="{{ $loanProduct->id }}" data-penalties="{{ $loanProduct->late_payment_penalties }}" data-loan-id="{{ $loanProduct->loan_id_prefix.$loanProduct->starting_loan_id }}" data-details="{{ $loanProduct }}">{{ $loanProduct->name }}</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Currency') }}</label>
									<select class="form-control auto-select" data-selected="{{ old('currency_id') }}" name="currency_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\Currency::where('status', 1)->get() as $currency)
										<option value="{{ $currency->id }}">{{ $currency->full_name }} ({{ $currency->name }})</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('First Payment Date') }}</label>
									<input type="text" class="form-control datepicker" name="first_payment_date" value="{{ old('first_payment_date') }}" required>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Applied Amount') }}</label>
									<input type="text" class="form-control float-field" name="applied_amount" value="{{ old('applied_amount') }}" required>
								</div>
							</div>

							<div class="col-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Purpose of Loan') }}</label>
									<textarea class="form-control" name="loan_purpose" rows="3" placeholder="Describe how you plan to use the loan funds" required>{{ old('loan_purpose') }}</textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Guarantor Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-user-friends"></i> Guarantor Information</h5>
						<div class="row">
							<div class="col-12">
								<div class="form-check mb-3">
									<input type="checkbox" class="form-check-input" name="require_guarantor" id="require_guarantor" value="1" {{ old('require_guarantor') ? 'checked' : '' }}>
									<label class="form-check-label" for="require_guarantor">
										<strong>{{ _lang('I need a guarantor for this loan') }}</strong>
									</label>
								</div>
							</div>
						</div>
						
						<div id="guarantor_details" style="display: none;">
							<div class="row">
								<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Guarantor Email') }}</label>
									<input type="email" class="form-control" name="guarantor_email" value="{{ old('guarantor_email') }}" placeholder="Enter guarantor's email address">
									<small class="form-text text-muted">
										<i class="fas fa-info-circle"></i> {{ _lang('Guarantor must be a member of the same organization') }}. 
										{{ _lang('The system will verify this before sending the invitation') }}.
									</small>
								</div>
								</div>
								
								<div class="col-lg-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Guarantor Name') }}</label>
										<input type="text" class="form-control" name="guarantor_name" value="{{ old('guarantor_name') }}" placeholder="Enter guarantor's full name">
									</div>
								</div>
								
								<div class="col-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Guarantor Message') }}</label>
										<textarea class="form-control" name="guarantor_message" rows="3" placeholder="Personal message to your guarantor">{{ old('guarantor_message', 'I would like to request you to be my guarantor for this loan application. Your support would be greatly appreciated.') }}</textarea>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Additional Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-info-circle"></i> Additional Information</h5>
						<div class="row">
							<div class="col-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Description') }}</label>
									<textarea class="form-control" name="description" rows="3" placeholder="Additional information about your loan application">{{ old('description') }}</textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Account and Documents -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-cogs"></i> Account & Documents</h5>
						<div class="row">

							<!--Custom Fields-->
							@if(! $customFields->isEmpty())
								@foreach($customFields as $customField)
								<div class="{{ $customField->field_width }}">
									<div class="form-group">
										<label class="control-label">{{ $customField->field_name }}</label>	
										{!! xss_clean(generate_input_field($customField)) !!}
									</div>
								</div>
								@endforeach
	                        @endif

							@if($accounts->count() > 1)
							<div class="col-lg-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Fee Deduct Account') }}</label>
									<select class="form-control auto-select select2" data-selected="{{ old('debit_account_id') }}" name="debit_account_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach($accounts as $account)
	                                        <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
	                                    @endforeach
									</select>
								</div>
							</div>
							@elseif($accounts->count() == 1)
							<!-- Single account - automatically selected -->
							<div class="col-lg-12">
								<div class="alert alert-info">
									<i class="fas fa-info-circle"></i>
									<strong>{{ _lang('Fee Deduct Account') }}:</strong> 
									{{ $accounts->first()->account_number }} ({{ $accounts->first()->savings_type->name }} - {{ $accounts->first()->savings_type->currency->name }})
									<small class="d-block text-muted mt-1">{{ _lang('Fees will be deducted from your only account') }}</small>
								</div>
								<input type="hidden" name="debit_account_id" value="{{ $accounts->first()->id }}">
							</div>
							@else
							<!-- No accounts available -->
							<div class="col-lg-12">
								<div class="alert alert-warning">
									<i class="fas fa-exclamation-triangle"></i>
									<strong>{{ _lang('No Accounts Available') }}</strong>
									<p class="mb-0">{{ _lang('You need at least one savings account to apply for a loan. Please create a savings account first.') }}</p>
								</div>
							</div>
							@endif

							<div class="col-lg-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Supporting Documents') }}</label>
									<input type="file" class="file-uploader" name="attachment">
									<small class="form-text text-muted">Any supporting documents (PDF, JPG, PNG - Max 8MB)</small>
								</div>
							</div>
						</div>
					</div>

					<!-- Terms and Conditions -->
					<div class="form-section mb-4">
						<div class="form-check">
							<input type="checkbox" name="terms_accepted" id="terms_accepted" class="form-check-input" required>
							<label class="form-check-label" for="terms_accepted">
								I agree to the 
								<a href="#" data-toggle="modal" data-target="#termsModal" id="termsLink">Terms and Conditions</a> 
								and 
								<a href="#" data-toggle="modal" data-target="#privacyModal" id="privacyLink">Privacy Policy</a> 
								<span class="text-danger">*</span>
							</label>
						</div>
						<small class="text-muted">
							<i class="fas fa-info-circle"></i> 
							Terms and conditions will be loaded based on your selected loan product.
						</small>
					</div>

					<!-- Submit Button -->
					<div class="form-section text-center">
						<button type="submit" class="btn btn-primary btn-lg px-5">
							<i class="fas fa-paper-plane"></i> {{ _lang('Submit Application') }}
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

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" role="dialog" aria-labelledby="termsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="termsModalLabel">
					Terms and Conditions
					<small class="text-muted" id="termsProductInfo"></small>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body" style="max-height: 500px; overflow-y: auto;">
				@if($defaultTerms)
					{!! $defaultTerms->terms_and_conditions !!}
				@else
					<div class="alert alert-warning">
						<p><strong>Standard Loan Terms and Conditions</strong></p>
						<p><strong>Governing Law:</strong> Laws of the Republic of Kenya</p>
						<p><strong>Regulatory Compliance:</strong> Central Bank of Kenya (CBK) Guidelines</p>
						
						<p>By applying for this loan, you agree to the following terms and conditions:</p>
						<ul>
							<li>You must be a Kenyan citizen or resident with valid identification</li>
							<li>Minimum age of 18 years as per the Constitution of Kenya</li>
							<li>Valid Kenya Revenue Authority (KRA) PIN certificate required</li>
							<li>All information provided must be accurate and verifiable</li>
							<li>Consent to credit bureau checks as per the Credit Reference Bureau Act, 2013</li>
							<li>Interest rates calculated as per CBK guidelines and Banking Act</li>
							<li>Late payments attract penalties as per CBK guidelines (maximum 1% per month)</li>
							<li>We comply with the Data Protection Act, 2019 for your personal information</li>
							<li>Disputes will be resolved through arbitration under the Arbitration Act</li>
							<li>We are licensed and regulated by the Central Bank of Kenya</li>
						</ul>
						<p><em>Please contact our support team for the complete terms and conditions.</em></p>
					</div>
				@endif
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- Privacy Policy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" role="dialog" aria-labelledby="privacyModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="privacyModalLabel">
					Privacy Policy
					<small class="text-muted" id="privacyProductInfo"></small>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body" style="max-height: 500px; overflow-y: auto;">
				@if($defaultTerms)
					{!! $defaultTerms->privacy_policy !!}
				@else
					<div class="alert alert-warning">
						<p><strong>Privacy Policy</strong></p>
						<p><strong>Governing Law:</strong> Data Protection Act, 2019 (Kenya)</p>
						<p><strong>Regulatory Compliance:</strong> Office of the Data Protection Commissioner (ODPC)</p>
						
						<p>We are committed to protecting your privacy and personal information in accordance with Kenyan law:</p>
						<ul>
							<li>We collect personal data as required by the Banking Act and CBK guidelines</li>
							<li>Your data is processed in accordance with the Data Protection Act, 2019</li>
							<li>We may share information with credit reference bureaus as per the Credit Reference Bureau Act, 2013</li>
							<li>We comply with all CBK, KRA, and ODPC requirements</li>
							<li>You have rights under the Data Protection Act, 2019 including access, rectification, and erasure</li>
							<li>We implement appropriate security measures to protect your data</li>
							<li>We retain data as required by the Banking Act (7 years) and other applicable laws</li>
							<li>We will notify you of any data breaches as required by law</li>
							<li>You can lodge complaints with the Office of the Data Protection Commissioner</li>
						</ul>
						<p><em>Please contact our Data Protection Officer for the complete privacy policy.</em></p>
					</div>
				@endif
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<style>
.form-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    border: 1px solid #d4edda;
    border-radius: 8px;
    background: #f8fff8;
}

.section-title {
    color: #28a745;
    border-bottom: 2px solid #28a745;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
    font-weight: 600;
    font-size: 1.1rem;
}

.form-group label {
    font-weight: 500;
    color: #495057;
}

.form-control:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.btn-primary {
    background: linear-gradient(45deg, #28a745, #20c997);
    border: none;
    padding: 12px 30px;
    font-weight: 500;
}

.btn-primary:hover {
    background: linear-gradient(45deg, #20c997, #17a2b8);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.card-header {
    border-radius: 15px 15px 0 0 !important;
    background: linear-gradient(45deg, #28a745, #20c997) !important;
    color: white !important;
    padding: 1.5rem !important;
}

.card-header .panel-title {
    font-size: 1.5rem !important;
    font-weight: 700 !important;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.card-header p {
    font-size: 1rem !important;
    opacity: 0.95 !important;
    margin-top: 0.5rem !important;
}

.alert {
    border-radius: 8px;
    border: none;
}

.alert-info {
    background-color: #d1ecf1;
    border-left: 4px solid #28a745;
    color: #0c5460;
}

.alert-warning {
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
    color: #856404;
}

.alert-success {
    background-color: #d4edda;
    border-left: 4px solid #28a745;
    color: #155724;
}

/* Form validation styling */
.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

/* Terms and conditions styling */
.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.form-check-input:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

/* Submit button area */
.form-section.text-center {
    background: linear-gradient(135deg, #f8fff8, #e8f5e8);
    border: 2px solid #28a745;
    border-radius: 12px;
    padding: 2rem;
}

@media (max-width: 768px) {
    .form-section {
        padding: 1rem;
    }
    
    .section-title {
        font-size: 1.1rem;
    }
    
    .card-header .panel-title {
        font-size: 1.3rem !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Guarantor checkbox toggle
    const guarantorCheckbox = document.getElementById('require_guarantor');
    const guarantorDetails = document.getElementById('guarantor_details');
    
    if (guarantorCheckbox && guarantorDetails) {
        guarantorCheckbox.addEventListener('change', function() {
            if (this.checked) {
                guarantorDetails.style.display = 'block';
                // Make guarantor fields required when checked
                document.querySelector('input[name="guarantor_email"]').setAttribute('required', 'required');
                document.querySelector('input[name="guarantor_name"]').setAttribute('required', 'required');
            } else {
                guarantorDetails.style.display = 'none';
                // Remove required attribute when unchecked
                document.querySelector('input[name="guarantor_email"]').removeAttribute('required');
                document.querySelector('input[name="guarantor_name"]').removeAttribute('required');
            }
        });
        
        // Check if checkbox was already checked on page load
        if (guarantorCheckbox.checked) {
            guarantorDetails.style.display = 'block';
            document.querySelector('input[name="guarantor_email"]').setAttribute('required', 'required');
            document.querySelector('input[name="guarantor_name"]').setAttribute('required', 'required');
        }
    }

    // Form validation
    const form = document.getElementById('loanForm');
    if (form) {
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
    }

    // Remove invalid class on input
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });

    // Load terms and conditions based on loan product selection
    const loanProductSelect = document.querySelector('select[name="loan_product_id"]');
    if (loanProductSelect) {
        loanProductSelect.addEventListener('change', function() {
            const productId = this.value;
            if (productId) {
                loadTermsForProduct(productId);
            }
        });
    }

    function loadTermsForProduct(productId) {
        // Show loading state
        const termsModalBody = document.querySelector('#termsModal .modal-body');
        const privacyModalBody = document.querySelector('#privacyModal .modal-body');
        const termsProductInfo = document.querySelector('#termsProductInfo');
        const privacyProductInfo = document.querySelector('#privacyProductInfo');
        
        if (termsModalBody) {
            termsModalBody.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading terms...</div>';
        }
        if (privacyModalBody) {
            privacyModalBody.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading privacy policy...</div>';
        }
        
        // Update modal titles to show loading
        if (termsProductInfo) {
            termsProductInfo.innerHTML = ' - Loading...';
        }
        if (privacyProductInfo) {
            privacyProductInfo.innerHTML = ' - Loading...';
        }

        // Fetch terms for the selected product
        fetch(`{{ route('loan_terms.get_terms_for_product') }}?product_id=${productId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.terms) {
                    // Update terms modal
                    if (termsModalBody) {
                        termsModalBody.innerHTML = data.terms.terms_and_conditions;
                    }
                    
                    // Update privacy modal
                    if (privacyModalBody) {
                        privacyModalBody.innerHTML = data.terms.privacy_policy;
                    }
                    
                    // Update modal titles with product info
                    if (termsProductInfo) {
                        termsProductInfo.innerHTML = ` - ${data.terms.title || 'Product Terms'}`;
                    }
                    if (privacyProductInfo) {
                        privacyProductInfo.innerHTML = ` - ${data.terms.title || 'Product Privacy Policy'}`;
                    }
                } else {
                    // Show fallback message
                    if (termsModalBody) {
                        termsModalBody.innerHTML = '<div class="alert alert-info">No specific terms found for this product. Please contact support for details.</div>';
                    }
                    if (privacyModalBody) {
                        privacyModalBody.innerHTML = '<div class="alert alert-info">No specific privacy policy found for this product. Please contact support for details.</div>';
                    }
                    
                    // Reset modal titles
                    if (termsProductInfo) {
                        termsProductInfo.innerHTML = '';
                    }
                    if (privacyProductInfo) {
                        privacyProductInfo.innerHTML = '';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading terms:', error);
                // Show error message
                if (termsModalBody) {
                    termsModalBody.innerHTML = '<div class="alert alert-danger">Error loading terms. Please try again or contact support.</div>';
                }
                if (privacyModalBody) {
                    privacyModalBody.innerHTML = '<div class="alert alert-danger">Error loading privacy policy. Please try again or contact support.</div>';
                }
                
                // Reset modal titles
                if (termsProductInfo) {
                    termsProductInfo.innerHTML = '';
                }
                if (privacyProductInfo) {
                    privacyProductInfo.innerHTML = '';
                }
            });
    }
});
</script>
@endsection
