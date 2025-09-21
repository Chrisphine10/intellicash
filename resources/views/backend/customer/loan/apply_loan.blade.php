@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Apply New Loan') }}</span>
				<p class="mb-0 mt-2 text-muted">Complete the comprehensive loan application form below</p>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('loans.apply_loan') }}" enctype="multipart/form-data" id="comprehensiveLoanForm">
					@csrf
					
					<!-- Basic Loan Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-clipboard-list"></i> Basic Loan Information</h5>
						<div class="row">

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Loan Product') }} <span class="text-danger">*</span></label>
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
									<label class="control-label">{{ _lang('Currency') }} <span class="text-danger">*</span></label>
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
									<label class="control-label">{{ _lang('First Payment Date') }} <span class="text-danger">*</span></label>
									<input type="text" class="form-control datepicker" name="first_payment_date" value="{{ old('first_payment_date') }}" required>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Applied Amount') }} <span class="text-danger">*</span></label>
									<input type="text" class="form-control float-field" name="applied_amount" value="{{ old('applied_amount') }}" required>
								</div>
							</div>

							<div class="col-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Purpose of Loan') }} <span class="text-danger">*</span></label>
									<textarea class="form-control" name="loan_purpose" rows="3" placeholder="Describe how you plan to use the loan funds" required>{{ old('loan_purpose') }}</textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Business Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-store"></i> Business Information</h5>
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Business Name') }}</label>
									<input type="text" class="form-control" name="business_name" value="{{ old('business_name') }}" placeholder="Enter business name">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Business Type') }}</label>
									<select class="form-control" name="business_type">
										<option value="">{{ _lang('Select business type') }}</option>
										<option value="retail" {{ old('business_type') == 'retail' ? 'selected' : '' }}>Retail</option>
										<option value="manufacturing" {{ old('business_type') == 'manufacturing' ? 'selected' : '' }}>Manufacturing</option>
										<option value="service" {{ old('business_type') == 'service' ? 'selected' : '' }}>Service</option>
										<option value="agriculture" {{ old('business_type') == 'agriculture' ? 'selected' : '' }}>Agriculture</option>
										<option value="technology" {{ old('business_type') == 'technology' ? 'selected' : '' }}>Technology</option>
										<option value="construction" {{ old('business_type') == 'construction' ? 'selected' : '' }}>Construction</option>
										<option value="hospitality" {{ old('business_type') == 'hospitality' ? 'selected' : '' }}>Hospitality</option>
										<option value="transport" {{ old('business_type') == 'transport' ? 'selected' : '' }}>Transport</option>
										<option value="other" {{ old('business_type') == 'other' ? 'selected' : '' }}>Other</option>
									</select>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Business Registration Number') }}</label>
									<input type="text" class="form-control" name="business_registration_number" value="{{ old('business_registration_number') }}" placeholder="Enter registration number">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Business Start Date') }}</label>
									<input type="date" class="form-control" name="business_start_date" value="{{ old('business_start_date') }}">
								</div>
							</div>

							<div class="col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Number of Employees') }}</label>
									<input type="number" class="form-control" name="number_of_employees" value="{{ old('number_of_employees') }}" placeholder="0" min="0">
								</div>
							</div>

							<div class="col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Monthly Revenue') }} ({{ currency_symbol() }})</label>
									<input type="number" class="form-control" name="monthly_revenue" value="{{ old('monthly_revenue') }}" placeholder="0" min="0">
								</div>
							</div>

							<div class="col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Monthly Expenses') }} ({{ currency_symbol() }})</label>
									<input type="number" class="form-control" name="monthly_expenses" value="{{ old('monthly_expenses') }}" placeholder="0" min="0">
								</div>
							</div>

							<div class="col-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Business Description') }}</label>
									<textarea class="form-control" name="business_description" rows="4" placeholder="Describe your business, products/services, and target market">{{ old('business_description') }}</textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Personal Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-user"></i> Personal Information</h5>
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Full Name') }} <span class="text-danger">*</span></label>
									<input type="text" class="form-control" name="applicant_name" value="{{ old('applicant_name', auth()->user()->name) }}" required>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Email Address') }} <span class="text-danger">*</span></label>
									<input type="email" class="form-control" name="applicant_email" value="{{ old('applicant_email', auth()->user()->email) }}" required>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Phone Number') }} <span class="text-danger">*</span></label>
									<input type="tel" class="form-control" name="applicant_phone" value="{{ old('applicant_phone', auth()->user()->mobile) }}" required>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('ID Number') }}</label>
									<input type="text" class="form-control" name="applicant_id_number" value="{{ old('applicant_id_number') }}" placeholder="Enter your ID number">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Date of Birth') }}</label>
									<input type="date" class="form-control" name="applicant_dob" value="{{ old('applicant_dob') }}">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Marital Status') }}</label>
									<select class="form-control" name="applicant_marital_status">
										<option value="">{{ _lang('Select marital status') }}</option>
										<option value="single" {{ old('applicant_marital_status') == 'single' ? 'selected' : '' }}>Single</option>
										<option value="married" {{ old('applicant_marital_status') == 'married' ? 'selected' : '' }}>Married</option>
										<option value="divorced" {{ old('applicant_marital_status') == 'divorced' ? 'selected' : '' }}>Divorced</option>
										<option value="widowed" {{ old('applicant_marital_status') == 'widowed' ? 'selected' : '' }}>Widowed</option>
									</select>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Number of Dependents') }}</label>
									<input type="number" class="form-control" name="applicant_dependents" value="{{ old('applicant_dependents', 0) }}" placeholder="0" min="0">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Employment Status') }}</label>
									<select class="form-control" name="employment_status">
										<option value="">{{ _lang('Select employment status') }}</option>
										<option value="self_employed" {{ old('employment_status') == 'self_employed' ? 'selected' : '' }}>Self Employed</option>
										<option value="employed" {{ old('employment_status') == 'employed' ? 'selected' : '' }}>Employed</option>
										<option value="unemployed" {{ old('employment_status') == 'unemployed' ? 'selected' : '' }}>Unemployed</option>
										<option value="student" {{ old('employment_status') == 'student' ? 'selected' : '' }}>Student</option>
										<option value="retired" {{ old('employment_status') == 'retired' ? 'selected' : '' }}>Retired</option>
									</select>
								</div>
							</div>

							<div class="col-lg-6" id="employment_details" style="display: none;">
								<div class="form-group">
									<label class="control-label">{{ _lang('Employer Name') }}</label>
									<input type="text" class="form-control" name="employer_name" value="{{ old('employer_name') }}" placeholder="Enter employer name">
								</div>
							</div>

							<div class="col-lg-6" id="employment_details2" style="display: none;">
								<div class="form-group">
									<label class="control-label">{{ _lang('Job Title') }}</label>
									<input type="text" class="form-control" name="job_title" value="{{ old('job_title') }}" placeholder="Enter job title">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Monthly Income') }} ({{ currency_symbol() }})</label>
									<input type="number" class="form-control" name="monthly_income" value="{{ old('monthly_income') }}" placeholder="0" min="0">
								</div>
							</div>

							<div class="col-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Address') }} <span class="text-danger">*</span></label>
									<textarea class="form-control" name="applicant_address" rows="3" placeholder="Enter your full address" required>{{ old('applicant_address') }}</textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Collateral Information -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-shield-alt"></i> Collateral Information</h5>
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Collateral Type') }}</label>
									<select class="form-control" name="collateral_type">
										<option value="">{{ _lang('Select collateral type') }}</option>
										<option value="bank_statement" {{ old('collateral_type') == 'bank_statement' ? 'selected' : '' }}>Bank Statement</option>
										<option value="payroll" {{ old('collateral_type') == 'payroll' ? 'selected' : '' }}>Payroll</option>
										<option value="property" {{ old('collateral_type') == 'property' ? 'selected' : '' }}>Property</option>
										<option value="vehicle" {{ old('collateral_type') == 'vehicle' ? 'selected' : '' }}>Vehicle</option>
										<option value="equipment" {{ old('collateral_type') == 'equipment' ? 'selected' : '' }}>Equipment</option>
										<option value="inventory" {{ old('collateral_type') == 'inventory' ? 'selected' : '' }}>Inventory</option>
										<option value="guarantor" {{ old('collateral_type') == 'guarantor' ? 'selected' : '' }}>Guarantor</option>
									</select>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Collateral Value') }} ({{ currency_symbol() }})</label>
									<input type="number" class="form-control" name="collateral_value" value="{{ old('collateral_value') }}" placeholder="0" min="0">
								</div>
							</div>

							<div class="col-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Collateral Description') }}</label>
									<textarea class="form-control" name="collateral_description" rows="3" placeholder="Describe the collateral in detail">{{ old('collateral_description') }}</textarea>
								</div>
							</div>
						</div>
					</div>

					<!-- Guarantor Information -->
					<div class="form-section mb-4" id="guarantor_section" style="display: none;">
						<h5 class="section-title text-primary"><i class="fas fa-user-friends"></i> Guarantor Information</h5>
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Guarantor Name') }}</label>
									<input type="text" class="form-control" name="guarantor_name" value="{{ old('guarantor_name') }}" placeholder="Enter guarantor's full name">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Guarantor Phone') }}</label>
									<input type="tel" class="form-control" name="guarantor_phone" value="{{ old('guarantor_phone') }}" placeholder="Enter guarantor's phone number">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Guarantor Email') }}</label>
									<input type="email" class="form-control" name="guarantor_email" value="{{ old('guarantor_email') }}" placeholder="Enter guarantor's email">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Relationship') }}</label>
									<input type="text" class="form-control" name="guarantor_relationship" value="{{ old('guarantor_relationship') }}" placeholder="e.g., Friend, Family, Business Partner">
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Guarantor Monthly Income') }} ({{ currency_symbol() }})</label>
									<input type="number" class="form-control" name="guarantor_income" value="{{ old('guarantor_income') }}" placeholder="0" min="0">
								</div>
							</div>
						</div>
					</div>

					<!-- Document Upload -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-file-upload"></i> Required Documents</h5>
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Business Documents') }}</label>
									<input type="file" class="form-control" name="business_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
									<small class="form-text text-muted">Business registration, permits, etc. (PDF, JPG, PNG - Max 5MB each)</small>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Financial Documents') }}</label>
									<input type="file" class="form-control" name="financial_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
									<small class="form-text text-muted">Bank statements, financial statements (PDF, JPG, PNG - Max 5MB each)</small>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Personal Documents') }}</label>
									<input type="file" class="form-control" name="personal_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
									<small class="form-text text-muted">ID, proof of address, etc. (PDF, JPG, PNG - Max 5MB each)</small>
								</div>
							</div>

							<div class="col-lg-6">
								<div class="form-group">
									<label class="control-label">{{ _lang('Collateral Documents') }}</label>
									<input type="file" class="form-control" name="collateral_documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
									<small class="form-text text-muted">Property deeds, vehicle logbook, etc. (PDF, JPG, PNG - Max 5MB each)</small>
								</div>
							</div>
						</div>
					</div>

					<!-- Existing Fields -->
					<div class="form-section mb-4">
						<h5 class="section-title text-primary"><i class="fas fa-cogs"></i> Additional Information</h5>
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

							<div class="col-lg-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Fee Deduct Account') }} <span class="text-danger">*</span></label>
									<select class="form-control auto-select select2" data-selected="{{ old('debit_account_id') }}" name="debit_account_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach($accounts as $account)
	                                        <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
	                                    @endforeach
									</select>
								</div>
							</div>

							<div class="col-lg-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Additional Attachment') }}</label>
									<input type="file" class="file-uploader" name="attachment">
									<small class="form-text text-muted">Any additional supporting documents (PDF, JPG, PNG - Max 8MB)</small>
								</div>
							</div>

							<div class="col-lg-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Description') }}</label>
									<textarea class="form-control" name="description" rows="3" placeholder="Additional information about your loan application">{{ old('description') }}</textarea>
								</div>
							</div>

							<div class="col-lg-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Remarks') }}</label>
									<textarea class="form-control" name="remarks" rows="3" placeholder="Any additional remarks or special requests">{{ old('remarks') }}</textarea>
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
    color: white !important;
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
    const employmentStatusSelect = document.querySelector('select[name="employment_status"]');
    const employmentDetailsDiv = document.getElementById('employment_details');
    const employmentDetailsDiv2 = document.getElementById('employment_details2');
    const guarantorSection = document.getElementById('guarantor_section');
    const collateralTypeSelect = document.querySelector('select[name="collateral_type"]');

    // Show/hide employment details
    if (employmentStatusSelect) {
        employmentStatusSelect.addEventListener('change', function() {
            if (this.value === 'employed') {
                employmentDetailsDiv.style.display = 'block';
                employmentDetailsDiv2.style.display = 'block';
            } else {
                employmentDetailsDiv.style.display = 'none';
                employmentDetailsDiv2.style.display = 'none';
            }
        });
    }

    // Show/hide guarantor section based on collateral type
    if (collateralTypeSelect) {
        collateralTypeSelect.addEventListener('change', function() {
            if (this.value === 'guarantor') {
                guarantorSection.style.display = 'block';
            } else {
                guarantorSection.style.display = 'none';
            }
        });
    }

    // Form validation
    const form = document.getElementById('comprehensiveLoanForm');
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
