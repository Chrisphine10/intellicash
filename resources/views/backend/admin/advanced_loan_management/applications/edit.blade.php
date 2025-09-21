@extends('layouts.app')

@section('title', 'Edit Application')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('advanced_loan_management.index') }}">Advanced Loan Management</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('advanced_loan_management.applications') }}">Applications</a></li>
                        <li class="breadcrumb-item active">Edit Application</li>
                    </ol>
                </div>
                <h4 class="page-title">Edit Application - {{ $application->application_number }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('advanced_loan_management.applications.update', $application->id) }}">
                        @csrf
                        @method('PUT')
                        
                        <!-- Loan Product Selection -->
                        <div class="form-group">
                            <label for="loan_product_id">Loan Product <span class="text-danger">*</span></label>
                            <select name="loan_product_id" id="loan_product_id" class="form-control" required>
                                <option value="">Choose a loan product...</option>
                                @foreach($loanProducts as $product)
                                    <option value="{{ $product->id }}" {{ $application->loan_product_id == $product->id ? 'selected' : '' }}>
                                        {{ $product->name }} - KES {{ number_format($product->minimum_amount, 0) }} - KES {{ number_format($product->maximum_amount, 0) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="requested_amount">Requested Amount (KES) <span class="text-danger">*</span></label>
                                    <input type="number" name="requested_amount" id="requested_amount" class="form-control" 
                                           value="{{ old('requested_amount', $application->requested_amount) }}" required min="1000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="business_type">Business Type <span class="text-danger">*</span></label>
                                    <select name="business_type" id="business_type" class="form-control" required>
                                        <option value="">Select business type...</option>
                                        <option value="retail" {{ $application->business_type == 'retail' ? 'selected' : '' }}>Retail</option>
                                        <option value="manufacturing" {{ $application->business_type == 'manufacturing' ? 'selected' : '' }}>Manufacturing</option>
                                        <option value="service" {{ $application->business_type == 'service' ? 'selected' : '' }}>Service</option>
                                        <option value="agriculture" {{ $application->business_type == 'agriculture' ? 'selected' : '' }}>Agriculture</option>
                                        <option value="technology" {{ $application->business_type == 'technology' ? 'selected' : '' }}>Technology</option>
                                        <option value="construction" {{ $application->business_type == 'construction' ? 'selected' : '' }}>Construction</option>
                                        <option value="hospitality" {{ $application->business_type == 'hospitality' ? 'selected' : '' }}>Hospitality</option>
                                        <option value="transport" {{ $application->business_type == 'transport' ? 'selected' : '' }}>Transport</option>
                                        <option value="other" {{ $application->business_type == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="business_name">Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="business_name" id="business_name" class="form-control" 
                                   value="{{ old('business_name', $application->business_name) }}" required>
                        </div>

                        <div class="form-group">
                            <label for="loan_purpose">Purpose of Loan <span class="text-danger">*</span></label>
                            <textarea name="loan_purpose" id="loan_purpose" class="form-control" rows="3" required>{{ old('loan_purpose', $application->loan_purpose) }}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="business_description">Business Description <span class="text-danger">*</span></label>
                            <textarea name="business_description" id="business_description" class="form-control" rows="4" required>{{ old('business_description', $application->business_description) }}</textarea>
                        </div>

                        <!-- Applicant Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="applicant_name">Applicant Name <span class="text-danger">*</span></label>
                                    <input type="text" name="applicant_name" id="applicant_name" class="form-control" 
                                           value="{{ old('applicant_name', $application->applicant_name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="applicant_email">Applicant Email <span class="text-danger">*</span></label>
                                    <input type="email" name="applicant_email" id="applicant_email" class="form-control" 
                                           value="{{ old('applicant_email', $application->applicant_email) }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="applicant_phone">Applicant Phone <span class="text-danger">*</span></label>
                                    <input type="tel" name="applicant_phone" id="applicant_phone" class="form-control" 
                                           value="{{ old('applicant_phone', $application->applicant_phone) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="monthly_income">Monthly Income (KES)</label>
                                    <input type="number" name="monthly_income" id="monthly_income" class="form-control" 
                                           value="{{ old('monthly_income', $application->monthly_income) }}" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="applicant_address">Address <span class="text-danger">*</span></label>
                            <textarea name="applicant_address" id="applicant_address" class="form-control" rows="3" required>{{ old('applicant_address', $application->applicant_address) }}</textarea>
                        </div>

                        <!-- Collateral Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="collateral_type">Collateral Type <span class="text-danger">*</span></label>
                                    <select name="collateral_type" id="collateral_type" class="form-control" required>
                                        <option value="">Select collateral type...</option>
                                        <option value="bank_statement" {{ $application->collateral_type == 'bank_statement' ? 'selected' : '' }}>Bank Statement</option>
                                        <option value="payroll" {{ $application->collateral_type == 'payroll' ? 'selected' : '' }}>Payroll</option>
                                        <option value="property" {{ $application->collateral_type == 'property' ? 'selected' : '' }}>Property</option>
                                        <option value="vehicle" {{ $application->collateral_type == 'vehicle' ? 'selected' : '' }}>Vehicle</option>
                                        <option value="equipment" {{ $application->collateral_type == 'equipment' ? 'selected' : '' }}>Equipment</option>
                                        <option value="inventory" {{ $application->collateral_type == 'inventory' ? 'selected' : '' }}>Inventory</option>
                                        <option value="guarantor" {{ $application->collateral_type == 'guarantor' ? 'selected' : '' }}>Guarantor</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="collateral_value">Collateral Value (KES)</label>
                                    <input type="number" name="collateral_value" id="collateral_value" class="form-control" 
                                           value="{{ old('collateral_value', $application->collateral_value) }}" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="collateral_description">Collateral Description</label>
                            <textarea name="collateral_description" id="collateral_description" class="form-control" rows="3">{{ old('collateral_description', $application->collateral_description) }}</textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Application
                            </button>
                            <a href="{{ route('advanced_loan_management.applications.show', $application->id) }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
