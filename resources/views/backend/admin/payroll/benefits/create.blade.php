@extends('layouts.app')

@section('title', _lang('Add Payroll Benefit'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Add Payroll Benefit') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.benefits.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Benefits') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('payroll.benefits.store') }}" method="POST" id="benefit-form">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">{{ _lang('Benefit Name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="{{ old('name') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code">{{ _lang('Code') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" 
                                       value="{{ old('code') }}" required>
                                <small class="form-text text-muted">{{ _lang('Unique code for this benefit') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">{{ _lang('Description') }}</label>
                        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type">{{ _lang('Type') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="type" name="type" required>
                                    <option value="">{{ _lang('Select Type') }}</option>
                                    @foreach($types as $key => $value)
                                        <option value="{{ $key }}" {{ old('type') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">{{ _lang('Category') }}</label>
                                <select class="form-control" id="category" name="category">
                                    <option value="">{{ _lang('Select Category') }}</option>
                                    @foreach($categories as $key => $value)
                                        <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row" id="rate-amount-fields">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="rate">{{ _lang('Rate (%)') }}</label>
                                <input type="number" class="form-control" id="rate" name="rate" 
                                       value="{{ old('rate') }}" step="0.01" min="0" max="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="amount">{{ _lang('Fixed Amount') }}</label>
                                <input type="number" class="form-control" id="amount" name="amount" 
                                       value="{{ old('amount') }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="minimum_amount">{{ _lang('Minimum Amount') }}</label>
                                <input type="number" class="form-control" id="minimum_amount" name="minimum_amount" 
                                       value="{{ old('minimum_amount') }}" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="maximum_amount">{{ _lang('Maximum Amount') }}</label>
                                <input type="number" class="form-control" id="maximum_amount" name="maximum_amount" 
                                       value="{{ old('maximum_amount') }}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="effective_date">{{ _lang('Effective Date') }}</label>
                                <input type="date" class="form-control" id="effective_date" name="effective_date" 
                                       value="{{ old('effective_date') }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="expiry_date">{{ _lang('Expiry Date') }}</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                       value="{{ old('expiry_date') }}">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_employer_paid" name="is_employer_paid" 
                                           value="1" {{ old('is_employer_paid') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_employer_paid">
                                        {{ _lang('Employer Paid Benefit') }}
                                    </label>
                                    <small class="form-text text-muted">{{ _lang('Check if this benefit is paid by the employer') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ _lang('Save Benefit') }}
                            </button>
                            <a href="{{ route('payroll.benefits.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Show/hide fields based on type
    $('#type').change(function() {
        var type = $(this).val();
        
        if (type === 'percentage') {
            $('#rate').closest('.form-group').show();
            $('#amount').closest('.form-group').hide();
        } else if (type === 'fixed_amount') {
            $('#rate').closest('.form-group').hide();
            $('#amount').closest('.form-group').show();
        } else {
            $('#rate').closest('.form-group').show();
            $('#amount').closest('.form-group').show();
        }
    });
    
    // Trigger change on page load
    $('#type').trigger('change');
    
    // Form validation
    $('#benefit-form').on('submit', function(e) {
        var isValid = true;
        
        // Check required fields
        $('input[required], select[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            toastr.error('{{ _lang("Please fill in all required fields") }}');
        }
    });
    
    // Remove validation classes on input
    $('input, select').on('input change', function() {
        $(this).removeClass('is-invalid');
    });
});
</script>
@endsection
