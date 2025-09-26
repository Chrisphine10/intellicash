@extends('layouts.app')

@section('title', _lang('Payroll Module Configuration'))

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Payroll Module Configuration') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('modules.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Modules') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('modules.payroll.update') }}" method="POST" id="payroll-config-form">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title">{{ _lang('Employee Account Configuration') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label for="employee_account_type">{{ _lang('Employee Account Type') }} <span class="text-danger">*</span></label>
                                        <select class="form-control" id="employee_account_type" name="employee_account_type" required>
                                            <option value="system_users" {{ $employeeAccountType === 'system_users' ? 'selected' : '' }}>
                                                {{ _lang('System Users') }}
                                            </option>
                                            <option value="member_accounts" {{ $employeeAccountType === 'member_accounts' ? 'selected' : '' }}>
                                                {{ _lang('Member Accounts') }}
                                            </option>
                                        </select>
                                        <small class="form-text text-muted">
                                            {{ _lang('Choose whether employees should be created as system users or linked to member accounts') }}
                                        </small>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> {{ _lang('Configuration Details') }}</h6>
                                        <ul class="mb-0">
                                            <li><strong>{{ _lang('System Users') }}:</strong> {{ _lang('Employees will be linked to existing system user accounts. This allows employees to have login access to the system.') }}</li>
                                            <li><strong>{{ _lang('Member Accounts') }}:</strong> {{ _lang('Employees will be linked to existing member accounts. This is useful when employees are also members of the organization.') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Save Configuration') }}
                                </button>
                                <a href="{{ route('modules.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    $('#payroll-config-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = form.serialize();
        
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.includes('success')) {
                    // Show success message
                    toastr.success('{{ _lang("Payroll configuration updated successfully") }}');
                    
                    // Redirect back to modules after a short delay
                    setTimeout(function() {
                        window.location.href = '{{ route("modules.index") }}';
                    }, 1500);
                } else {
                    toastr.error('{{ _lang("An error occurred while updating the configuration") }}');
                }
            },
            error: function() {
                toastr.error('{{ _lang("An error occurred while updating the configuration") }}');
            }
        });
    });
    
    // Show confirmation when changing account type
    $('#employee_account_type').on('change', function() {
        var newValue = $(this).val();
        var currentValue = '{{ $employeeAccountType }}';
        
        if (newValue !== currentValue) {
            var message = newValue === 'system_users' 
                ? '{{ _lang("Switching to System Users will allow employees to have login access. Existing employee-member links will be cleared. Continue?") }}'
                : '{{ _lang("Switching to Member Accounts will link employees to existing members. Existing employee-user links will be cleared. Continue?") }}';
                
            if (!confirm(message)) {
                $(this).val(currentValue);
            }
        }
    });
});
</script>
@endsection
