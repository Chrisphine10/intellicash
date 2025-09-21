@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Update Tenant') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('admin.tenants.update', $id) }}" enctype="multipart/form-data">
					@csrf
					<input name="_method" type="hidden" value="PATCH">
					<div class="row">
                        <div class="col-lg-12">
                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Name') }}</label>						
                                <div class="col-xl-9">
                                    <input type="text" class="form-control" name="name" value="{{ $tenant->name }}" required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Workspace') }}</label>						
                                <div class="col-xl-9">
                                    <div class="form-control pr-0 d-flex align-items-center parent-box">
                                        <span class="text-nowrap">{{ parse_url(url(''), PHP_URL_HOST).'/' }}</span>
                                        <input type="text" class="form-control no-msg" name="workspace" value="{{ $tenant->slug }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Email') }}</label>
                                <div class="col-xl-9">
                                    <input type="email" class="form-control" name="email" value="{{ $tenant->owner->email }}" required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Password') }}</label>
                                <div class="col-xl-9">
                                    <input type="password" class="form-control" name="password" value="">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Membership Type') }}</label>						
                                <div class="col-xl-9">
                                    <select class="form-control auto-select" data-selected="{{ $tenant->membership_type }}" name="membership_type" required>
                                        <option value="member">{{ _lang('Member') }}</option>
                                        <option value="trial">{{ _lang('Trial') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Subscription Plan') }}</label>						
                                <div class="col-xl-9">
                                    <select class="form-control auto-select select2" data-selected="{{ $tenant->package_id }}" name="package_id" required>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        @foreach(\App\Models\Package::active()->get() as $package)
                                        <option value="{{ $package->id }}">{{ $package->name }} ({{ decimalPlace($package->cost, currency_symbol()).'/'.ucwords($package->package_type) }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Expiry Date') }}</label>						
                                <div class="col-xl-9">
                                    <input type="text" class="form-control datepicker" name="valid_to" value="{{ $tenant->getRawOriginal('valid_to') }}" required>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>						
                                <div class="col-xl-9">
                                    <select class="form-control auto-select" data-selected="{{ $tenant->status }}" name="status" required>
                                        <option value="1">{{ _lang('Active') }}</option>
                                        <option value="0">{{ _lang('Deactivated') }}</option>
                                    </select>
                                </div>
                            </div>
            
                            <div class="form-group row mt-4">
                                <div class="col-xl-9 offset-xl-3">
                                    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i> {{ _lang('Update') }}</button>
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
<script>
(function ($) {
    "use strict";

    let timer = null;

    $('input[name="workspace"]').on('keyup', function(){
        clearTimeout(timer);
        var workspace = $(this).val().trim();
        var $inputField = $(this);

        if (workspace.length > 0) {
            timer = setTimeout(function() {
                $.ajax({
                    url: "{{ route('check-slug') }}",
                    method: "GET",
                    data: { workspace: workspace },
                    success: function(response) {
                        if (response.exists) {
                            $inputField.parent().css("border", "1px solid #dc3545");
                            $inputField.parent().next('.success-msg').remove();
                            if (!$inputField.parent().next('.error-msg').length) {
                                $inputField.parent().after(`<small class="text-danger error-msg"><i class="fas fa-exclamation-circle mr-1"></i>${response.message}</small>`);
                            }else{
                                $inputField.parent().next('.error-msg').html(`<i class="fas fa-exclamation-circle mr-1"></i>${response.message}`);
                            }
                        } else {
                            $inputField.parent().css("border", "1px solid #28a745");
                            $inputField.parent().next('.error-msg').remove();
                            if (!$inputField.parent().next('.success-msg').length) {
                                $inputField.parent().after(`<small class="text-success success-msg"><i class="fas fa-check-circle mr-1"></i>${response.message}</small>`);
                            }else{
                                $inputField.parent().next('.success-msg').html(`<i class="fas fa-check-circle mr-1"></i>${response.message}`);
                            }
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            var errorMessage = errors ? Object.values(errors).join('<br>') : 'Invalid input!';

                            $inputField.parent().css("border", "1px solid #dc3545");
                            $inputField.parent().next('.success-msg').remove();
                            $inputField.parent().next('.error-msg').remove();
                            
                            $inputField.parent().after(`<small class="text-danger error-msg"><i class="fas fa-exclamation-circle mr-1"></i> ${errorMessage}</small>`);
                        }
                    }
                });
            }, 500);
        } else {
            $inputField.parent().css("border", "");
            $inputField.parent().next('.error-msg').remove();
        }
    });
})(jQuery);
</script>
@endsection