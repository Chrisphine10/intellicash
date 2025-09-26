@extends('layouts.app')

@section('title', _lang('Edit Employee'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Edit Employee') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-info btn-sm">
                        <i class="fas fa-eye"></i> {{ _lang('View Details') }}
                    </a>
                    <a href="{{ route('payroll.employees.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Employees') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('payroll.employees.update', $employee->id) }}" method="POST" id="employee-form">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">{{ _lang('Personal Information') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="first_name">{{ _lang('First Name') }} <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="{{ old('first_name', $employee->first_name) }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name">{{ _lang('Last Name') }} <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="{{ old('last_name', $employee->last_name) }}" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="middle_name">{{ _lang('Middle Name') }}</label>
                                        <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                               value="{{ old('middle_name', $employee->middle_name) }}">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email">{{ _lang('Email') }}</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="{{ old('email', $employee->email) }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">{{ _lang('Phone') }}</label>
                                                <input type="text" class="form-control" id="phone" name="phone" 
                                                       value="{{ old('phone', $employee->phone) }}">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="address">{{ _lang('Address') }}</label>
                                        <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $employee->address) }}</textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="date_of_birth">{{ _lang('Date of Birth') }}</label>
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                       value="{{ old('date_of_birth', $employee->date_of_birth) }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="gender">{{ _lang('Gender') }}</label>
                                                <select class="form-control" id="gender" name="gender">
                                                    <option value="">{{ _lang('Select Gender') }}</option>
                                                    <option value="male" {{ old('gender', $employee->gender) == 'male' ? 'selected' : '' }}>{{ _lang('Male') }}</option>
                                                    <option value="female" {{ old('gender', $employee->gender) == 'female' ? 'selected' : '' }}>{{ _lang('Female') }}</option>
                                                    <option value="other" {{ old('gender', $employee->gender) == 'other' ? 'selected' : '' }}>{{ _lang('Other') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="national_id">{{ _lang('National ID') }}</label>
                                        <input type="text" class="form-control" id="national_id" name="national_id" 
                                               value="{{ old('national_id', $employee->national_id) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employment Information -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">{{ _lang('Employment Information') }}</h5>
                                </div>
                                <div class="card-body">
                                    @if($employeeAccountType === 'system_users')
                                        <div class="form-group">
                                            <label for="user_id">{{ _lang('Link to System User') }}</label>
                                            <select class="form-control" id="user_id" name="user_id">
                                                <option value="">{{ _lang('Select System User') }}</option>
                                                @foreach($users as $user)
                                                    <option value="{{ $user->id }}" {{ old('user_id', $employee->user_id) == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }} ({{ $user->email }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted">{{ _lang('Link this employee to an existing system user account') }}</small>
                                        </div>
                                    @else
                                        <div class="form-group">
                                            <label for="member_id">{{ _lang('Link to Member Account') }}</label>
                                            <select class="form-control" id="member_id" name="member_id">
                                                <option value="">{{ _lang('Select Member') }}</option>
                                                @foreach($members as $member)
                                                    <option value="{{ $member->id }}" {{ old('member_id', $employee->member_id) == $member->id ? 'selected' : '' }}>
                                                        {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted">{{ _lang('Link this employee to an existing member account') }}</small>
                                        </div>
                                    @endif
                                    
                                    <div class="form-group">
                                        <label for="hire_date">{{ _lang('Hire Date') }} <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                               value="{{ old('hire_date', $employee->hire_date) }}" required>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="job_title">{{ _lang('Job Title') }} <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="job_title" name="job_title" 
                                                       value="{{ old('job_title', $employee->job_title) }}" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="department">{{ _lang('Department') }}</label>
                                                <input type="text" class="form-control" id="department" name="department" 
                                                       value="{{ old('department', $employee->department) }}">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="employment_type">{{ _lang('Employment Type') }} <span class="text-danger">*</span></label>
                                                <select class="form-control" id="employment_type" name="employment_type" required>
                                                    <option value="">{{ _lang('Select Type') }}</option>
                                                    @foreach($employmentTypes as $key => $value)
                                                        <option value="{{ $key }}" {{ old('employment_type', $employee->employment_type) == $key ? 'selected' : '' }}>
                                                            {{ $value }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="pay_frequency">{{ _lang('Pay Frequency') }} <span class="text-danger">*</span></label>
                                                <select class="form-control" id="pay_frequency" name="pay_frequency" required>
                                                    <option value="">{{ _lang('Select Frequency') }}</option>
                                                    @foreach($payFrequencies as $key => $value)
                                                        <option value="{{ $key }}" {{ old('pay_frequency', $employee->pay_frequency) == $key ? 'selected' : '' }}>
                                                            {{ $value }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="basic_salary">{{ _lang('Basic Salary') }} <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="basic_salary" name="basic_salary" 
                                                   value="{{ old('basic_salary', $employee->basic_salary) }}" step="0.01" min="0" required>
                                            <div class="input-group-append">
                                                <select class="form-control" name="salary_currency">
                                                    @php $currencies = \App\Models\Currency::active()->get(); @endphp
                                                    @foreach($currencies as $currency)
                                                        <option value="{{ $currency->name }}" {{ old('salary_currency', $employee->salary_currency) == $currency->name ? 'selected' : '' }}>
                                                            {{ $currency->name }} - {{ $currency->full_name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Bank Information -->
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">{{ _lang('Bank Information') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="bank_name">{{ _lang('Bank Name') }}</label>
                                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                                       value="{{ old('bank_name', $employee->bank_name) }}">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="bank_account_number">{{ _lang('Account Number') }}</label>
                                                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" 
                                                       value="{{ old('bank_account_number', $employee->bank_account_number) }}">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="bank_code">{{ _lang('Bank Code/Sort Code') }}</label>
                                                <input type="text" class="form-control" id="bank_code" name="bank_code" 
                                                       value="{{ old('bank_code', $employee->bank_code) }}" placeholder="Bank identification code">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="tax_number">{{ _lang('Tax Number/PIN') }}</label>
                                                <input type="text" class="form-control" id="tax_number" name="tax_number" 
                                                       value="{{ old('tax_number', $employee->tax_number) }}" placeholder="Tax identification number">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="insurance_number">{{ _lang('Insurance Number') }}</label>
                                                <input type="text" class="form-control" id="insurance_number" name="insurance_number" 
                                                       value="{{ old('insurance_number', $employee->insurance_number) }}" placeholder="Health/Social insurance number">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ _lang('Update Employee') }}
                            </button>
                            <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-secondary">
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
    // Form validation
    $('#employee-form').on('submit', function(e) {
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
