@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('public/backend/plugins/intl-tel-input/css/intlTelInput.css') }}"/>
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Add New User') }}</span>
                <div class="card-tools">
                    <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Users') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('users.store') }}"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Name') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                        required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Email') }}</label>
                                <div class="col-xl-9">
                                    <input type="email" class="form-control" name="email" value="{{ old('email') }}"
                                        required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Password') }}</label>
                                <div class="col-xl-9">
                                    <input type="password" class="form-control" name="password" value="" required minlength="8">
                                    <small class="text-muted">{{ _lang('Password must be at least 8 characters long') }}</small>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Confirm Password') }}</label>
                                <div class="col-xl-9">
                                    <input type="password" class="form-control" name="password_confirmation" value="" required minlength="8">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('User Type') }}</label>
                                <div class="col-xl-9">
                                    <select class="form-control auto-select" data-selected="{{ old('user_type') }}"
                                        name="user_type" id="user_type" required>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        <option value="admin">{{ _lang('Admin') }}</option>
                                        <option value="user">{{ _lang('User') }}</option>
                                    </select>
                                    <small class="text-primary"><i class="ti-info-alt"></i> <i>{{ _lang('Admin will get full access and user will get role based access only.') }}</i></small>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('User Role') }}</label>
                                <div class="col-xl-9">
                                    <select class="form-control" name="role_id" id="role_id" disabled>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        @foreach(\App\Models\Role::where('tenant_id', app('tenant')->id)->get() as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Branch') }}</label>
                                <div class="col-xl-9">
                                    <select class="form-control select2 auto-select" data-selected="{{ old('branch_id') }}" name="branch_id" id="user_branch_id">
                                        <option value="all_branch">{{ _lang('All Branch') }}</option>
                                        <option value="">{{ get_tenant_option('default_branch_name', 'Main Branch') }}</option>
                                        @foreach(\App\Models\Branch::all() as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-primary"><i class="ti-info-alt"></i> <i>{{ _lang('If not assign any branch then user will get default branch access.') }}</i></small>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>
                                <div class="col-xl-9">
                                    <select class="form-control auto-select" data-selected="{{ old('status', 1) }}"
                                        name="status" required>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        <option value="1">{{ _lang('Active') }}</option>
                                        <option value="0">{{ _lang('In Active') }}</option>
                                    </select>
                                    <a href="" class="mt-3 d-block toggle-optional-fields" data-toggle-title="{{ _lang('Hide Optional Fields') }}">{{ _lang('Show Optional Fields') }}</a>
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('Mobile') }}</label>

                                <div class="col-xl-3">
                                    <select class="form-control{{ $errors->has('country_code') ? ' is-invalid' : '' }} select2 no-msg" name="country_code">
                                        <option value="">{{ _lang('Country Code') }}</option>
                                        @foreach(get_country_codes() as $key => $value)
                                        <option value="{{ $value['dial_code'] }}" {{ old('country_code') == $value['dial_code'] ? 'selected' : '' }}>{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-xl-6 mt-2 mt-xl-0">
                                    <input id="mobile" type="tel" class="form-control" name="mobile" value="{{ old('mobile') }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('City') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="city" value="{{ old('city') }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('State') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="state" value="{{ old('state') }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('ZIP') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="zip" value="{{ old('zip') }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('Address') }}</label>
                                <div class="col-xl-9">
                                    <textarea class="form-control" name="address">{{ old('address') }}</textarea>
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('Profile Picture') }}</label>
                                <div class="col-xl-9">
                                    <input type="file" class="dropify" name="profile_picture">
                                </div>
                            </div>
    
                            <div class="form-group row mt-4">
                                <div class="col-xl-9 offset-xl-3">
                                    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Create User') }}</button>
                                </div>
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
<script src="{{ asset('public/backend/plugins/intl-tel-input/js/intlTelInput.min.js') }}"></script>

<script>
$(document).ready(function() {
    // Initialize phone input
    var input = document.querySelector("#mobile");
    if (input) {
        window.intlTelInput(input, {
            initialCountry: "auto",
            geoIpLookup: (success, failure) => {
                fetch("https://ipapi.co/json")
                .then((res) => res.json())
                .then((data) => success(data.country_code))
                .catch(() => failure());
            },
            countrySearch: false,
            separateDialCode: true,
            autoPlaceholder: "polite",
            nationalMode: false,
            utilsScript: "{{ asset('public/backend/plugins/intl-tel-input/js/utils.js') }}"
        });
    }

    // Enhanced user type and role selection logic
    function updateRoleSelection() {
        var userType = $('#user_type').val();
        var roleSelect = $('#role_id');
        var roleHelpText = $('#role-help-text');
        
        // Remove existing help text
        if (roleHelpText.length) {
            roleHelpText.remove();
        }
        
        if (userType === 'user') {
            // Enable role selection for regular users
            roleSelect.prop('disabled', false);
            roleSelect.attr('required', true);
            roleSelect.closest('.form-group').find('.col-xl-9').append(
                '<small id="role-help-text" class="text-info"><i class="ti-info-alt"></i> ' + 
                '{{ _lang("Regular users need a role to define their permissions") }}</small>'
            );
        } else if (userType === 'admin') {
            // Disable role selection for admins
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            roleSelect.val('');
            roleSelect.closest('.form-group').find('.col-xl-9').append(
                '<small id="role-help-text" class="text-success"><i class="ti-check"></i> ' + 
                '{{ _lang("Admins have full access and do not need role assignment") }}</small>'
            );
        } else {
            // Disable if no selection
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            roleSelect.val('');
        }
        
        // Re-validate the form
        if (typeof $('form.validate').parsley !== 'undefined') {
            $('form.validate').parsley().validate();
        }
    }
    
    // Handle user type change
    $('#user_type').on('change', updateRoleSelection);
    
    // Initialize on page load
    updateRoleSelection();
    
    // Password confirmation validation
    $('input[name="password_confirmation"]').on('input', function() {
        var password = $('input[name="password"]').val();
        var confirmation = $(this).val();
        
        if (password && confirmation && password !== confirmation) {
            $(this).addClass('is-invalid');
            if (!$(this).next('.invalid-feedback').length) {
                $(this).after('<div class="invalid-feedback">{{ _lang("Password confirmation does not match") }}</div>');
            }
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
    
    // Real-time password strength indicator
    $('input[name="password"]').on('input', function() {
        var password = $(this).val();
        var strengthIndicator = $('#password-strength');
        
        if (!strengthIndicator.length) {
            $(this).after('<div id="password-strength" class="mt-1"></div>');
            strengthIndicator = $('#password-strength');
        }
        
        var strength = 0;
        var feedback = [];
        
        if (password.length >= 8) strength++;
        else feedback.push('{{ _lang("At least 8 characters") }}');
        
        if (/[A-Z]/.test(password)) strength++;
        else feedback.push('{{ _lang("One uppercase letter") }}');
        
        if (/[a-z]/.test(password)) strength++;
        else feedback.push('{{ _lang("One lowercase letter") }}');
        
        if (/[0-9]/.test(password)) strength++;
        else feedback.push('{{ _lang("One number") }}');
        
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        else feedback.push('{{ _lang("One special character") }}');
        
        var strengthText = '';
        var strengthClass = '';
        
        if (strength <= 2) {
            strengthText = '{{ _lang("Weak") }}';
            strengthClass = 'text-danger';
        } else if (strength <= 3) {
            strengthText = '{{ _lang("Fair") }}';
            strengthClass = 'text-warning';
        } else if (strength <= 4) {
            strengthText = '{{ _lang("Good") }}';
            strengthClass = 'text-info';
        } else {
            strengthText = '{{ _lang("Strong") }}';
            strengthClass = 'text-success';
        }
        
        strengthIndicator.html(
            '<small class="' + strengthClass + '">' +
            '<i class="ti-shield"></i> ' + strengthText +
            (feedback.length > 0 ? ' - ' + feedback.join(', ') : '') +
            '</small>'
        );
    });
    
    // Form submission validation
    $('form.validate').on('submit', function(e) {
        var isValid = true;
        var errors = [];
        
        // Check password confirmation
        var password = $('input[name="password"]').val();
        var confirmation = $('input[name="password_confirmation"]').val();
        
        if (password !== confirmation) {
            errors.push('{{ _lang("Password confirmation does not match") }}');
            isValid = false;
        }
        
        // Check role requirement for users
        var userType = $('#user_type').val();
        var roleId = $('#role_id').val();
        
        if (userType === 'user' && !roleId) {
            errors.push('{{ _lang("Role is required for regular users") }}');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            
            // Show errors
            var errorHtml = '<div class="alert alert-danger"><ul class="mb-0">';
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            errorHtml += '</ul></div>';
            
            // Remove existing error alerts
            $('.alert-danger').remove();
            
            // Add new error alert
            $('form.validate').prepend(errorHtml);
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 500);
            
            return false;
        }
    });
    
    // Toggle optional fields
    $('.toggle-optional-fields').on('click', function(e) {
        e.preventDefault();
        var $this = $(this);
        var $fields = $('.optional-field');
        
        if ($fields.is(':visible')) {
            $fields.slideUp();
            $this.text('{{ _lang("Show Optional Fields") }}');
        } else {
            $fields.slideDown();
            $this.text('{{ _lang("Hide Optional Fields") }}');
        }
    });
});
</script>
@endsection