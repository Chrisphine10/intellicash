@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('public/backend/plugins/intl-tel-input/css/intlTelInput.css') }}"/>
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Add New User') }}</span>
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
                                    <input type="password" class="form-control" name="password" value="" required>
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
    var input = document.querySelector("#phone");
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

    // Debug: Check if elements exist
    console.log('User type element found:', $('#user_type').length);
    console.log('Role ID element found:', $('#role_id').length);
    
    // Handle user type change to enable/disable role selection
    $(document).on('change', '#user_type', function() {
        var userType = $(this).val();
        var roleSelect = $('#role_id');
        
        console.log('User type changed to:', userType);
        
        if (userType === 'user') {
            // Enable role selection for regular users
            roleSelect.prop('disabled', false);
            roleSelect.attr('required', true);
            console.log('Role dropdown enabled for user type');
        } else if (userType === 'admin') {
            // Disable role selection for admins
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            roleSelect.val('');
            console.log('Role dropdown disabled for admin type');
        } else {
            // Disable if no selection
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            roleSelect.val('');
            console.log('Role dropdown disabled - no user type selected');
        }
    });

    // Also try direct event binding
    $('#user_type').on('change', function() {
        console.log('Direct event binding triggered');
        var userType = $(this).val();
        var roleSelect = $('#role_id');
        
        if (userType === 'user') {
            roleSelect.prop('disabled', false);
            roleSelect.attr('required', true);
        } else {
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            if (userType !== 'user') {
                roleSelect.val('');
            }
        }
    });
});
</script>
@endsection