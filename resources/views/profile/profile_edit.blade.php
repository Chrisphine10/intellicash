@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('public/backend/plugins/intl-tel-input/css/intlTelInput.css') }}"/>
<div class="row">
	<div class="col-lg-10 offset-lg-1">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Profile Settings') }}</span>
			</div>
			<div class="card-body">
				@php $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : ''; @endphp
				<form action="{{ route($isAadminRoute.'profile.update') }}" autocomplete="off" class="form-horizontal form-group rows-bordered validate" enctype="multipart/form-data" method="post">
					@csrf
					<div class="row">
						<div class="col-lg-10">
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Name') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control" name="name" value="{{ $profile->name }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Email') }}</label>
								<div class="col-xl-9">
									<input type="email" class="form-control" name="email" value="{{ $profile->email }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Mobile') }}</label>
								<div class="col-xl-3">
                                    <select class="form-control{{ $errors->has('country_code') ? ' is-invalid' : '' }} select2 no-msg" name="country_code">
                                        <option value="">{{ _lang('Country Code') }}</option>
                                        @foreach(get_country_codes() as $key => $value)
                                        <option value="{{ $value['dial_code'] }}" {{ $profile->country_code == $value['dial_code'] ? 'selected' : '' }}>{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-xl-6 mt-2 mt-xl-0">
                                    <input id="mobile" type="tel" class="form-control" name="mobile" value="{{ $profile->mobile }}">
                                </div>
                            </div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('City') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control" name="city" value="{{ $profile->city }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('State') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control" name="state" value="{{ $profile->state }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('ZIP') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control" name="zip" value="{{ $profile->zip }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Address') }}</label>
								<div class="col-xl-9">
									<textarea class="form-control" name="address">{{ $profile->address }}</textarea>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Image') }} (300 X 300)</label>
								<div class="col-xl-9">
									<input type="file" class="form-control dropify" data-default-file="{{ $profile->profile_picture != "" ? asset('public/uploads/profile/'.$profile->profile_picture) : '' }}" name="profile_picture" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG">
								</div>
							</div>

							<div class="form-group row mt-2">
								<div class="col-xl-9 offset-lg-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Update Profile') }}</button>
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
});
</script>
@endsection