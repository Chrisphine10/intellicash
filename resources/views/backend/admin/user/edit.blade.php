@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('public/backend/plugins/intl-tel-input/css/intlTelInput.css') }}"/>
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Update User') }}</span>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off"
                    action="{{ route('users.update', $id) }}" enctype="multipart/form-data">
                    @csrf
                    <input name="_method" type="hidden" value="PATCH">

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Name') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="name" value="{{ $user->name }}"
                                        required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Email') }}</label>
                                <div class="col-xl-9">
                                    <input type="email" class="form-control" name="email" value="{{ $user->email }}"
                                        required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Mobile') }}</label>

                                <div class="col-xl-3">
                                    <select class="form-control{{ $errors->has('country_code') ? ' is-invalid' : '' }} select2 no-msg" name="country_code" required>
                                        <option value="">{{ _lang('Country Code') }}</option>
                                        @foreach(get_country_codes() as $key => $value)
                                        <option value="{{ $value['dial_code'] }}" {{ $user->country_code == $value['dial_code'] ? 'selected' : '' }}>{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-xl-6 mt-2 mt-xl-0">
                                    <input id="mobile" type="tel" class="form-control" name="mobile" value="{{ $user->mobile }}">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Password') }}</label>
                                <div class="col-xl-9">
                                    <input type="password" class="form-control" name="password">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('User Type') }}</label>
                                <div class="col-xl-9">
                                    <select class="form-control auto-select"
                                        data-selected="{{ $user->user_type }}" name="user_type" id="user_type" required>
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
                                    <select class="form-control select2-ajax auto-select" data-selected="{{ $user->role_id }}" data-href="{{ route('roles.create') }}" data-title="{{ _lang('Add New Role') }}" data-value="id" data-display="name"
                                        data-table="roles" name="role_id" id="role_id" {{ $user->user_type == 'admin' ? 'disabled' : '' }} {{ $user->user_type == 'user' ? 'required' : '' }}>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        @foreach(\App\Models\Role::where('tenant_id', app('tenant')->id)->get() as $role)
										<option value="{{ $role->id }}" {{ $user->role_id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
										@endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Branch') }}</label>
                                <div class="col-xl-9">
                                    <select class="form-control select2" name="branch_id" id="user_branch_id">
                                        <option value="all_branch" {{ $user->all_branch_access == 1 ? 'selected' : '' }}>{{ _lang('All Branch') }}</option>
                                        <option value="" {{ $user->all_branch_access == 0 && $user->branch_id == '' ? 'selected' : '' }}>{{ get_option('default_branch_name', 'Main Branch') }}</option>
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
                                    <select class="form-control auto-select" data-selected="{{ $user->status }}"
                                        name="status" required>
                                        <option value="1">{{ _lang('Active') }}</option>
                                        <option value="0">{{ _lang('In Active') }}</option>
                                    </select>
                                    <a href="" class="mt-3 d-block toggle-optional-fields" data-toggle-title="{{ _lang('Hide Optional Fields') }}">{{ _lang('Show Optional Fields') }}</a>
                                </div>
                            </div>


                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('City') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="city" value="{{ $user->city }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('State') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="state" value="{{ $user->state }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('ZIP') }}</label>
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="zip" value="{{ $user->zip }}">
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('Address') }}</label>
                                <div class="col-xl-9">
                                    <textarea class="form-control" name="address">{{ $user->address }}</textarea>
                                </div>
                            </div>

                            <div class="form-group row optional-field">
                                <label class="col-xl-3 col-form-label">{{ _lang('Profile Picture') }}</label>
                                <div class="col-xl-9">
                                    <input type="file" class="dropify" default="{{ $user->profile_picture }}" name="profile_picture">
                                </div>
                            </div>
    
                            <div class="form-group row mt-4">
                                <div class="col-xl-9 offset-xl-3">
                                    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Update User') }}</button>
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
document.addEventListener("DOMContentLoaded", function() {
    var input = document.querySelector("#phone");

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
    
    // Handle user type change to enable/disable role selection
    $('#user_type').on('change', function() {
        var userType = $(this).val();
        var roleSelect = $('#role_id');
        
        if (userType === 'user') {
            // Enable role selection for regular users
            roleSelect.prop('disabled', false);
            roleSelect.attr('required', true);
        } else if (userType === 'admin') {
            // Disable role selection for admins
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            roleSelect.val(''); // Clear selection
        } else {
            // Disable if no selection
            roleSelect.prop('disabled', true);
            roleSelect.removeAttr('required');
            roleSelect.val('');
        }
        
        // Re-validate the form after changing required attributes
        $('form.validate').parsley().validate();
    });
    
    // Trigger change event on page load to set initial state
    $('#user_type').trigger('change');
    
    // Debug form submission
    $('form.validate').on('submit', function(e) {
        console.log('Form submission started');
        console.log('Form data:', $(this).serialize());
        
        // Check if form is valid
        var isValid = $(this).parsley().isValid();
        console.log('Form is valid:', isValid);
        
        if (!isValid) {
            console.log('Form validation failed');
            var errors = $(this).parsley().getErrorsMessages();
            console.log('Validation errors:', errors);
            e.preventDefault();
            return false;
        }
        
        console.log('Form is valid, proceeding with submission');
    });
    
    // Debug form validation
    $('form.validate').parsley().on('form:validate', function() {
        console.log('Parsley validation triggered');
    });
    
    $('form.validate').parsley().on('form:submit', function() {
        console.log('Parsley form submit triggered');
    });
});
</script>
@endsection