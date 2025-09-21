@extends('layouts.auth')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card card-signin my-5 p-3">              
				<div class="card-body">
				    <img class="logo" src="{{ get_logo() }}">
					
					<h5 class="text-center py-4">{{ _lang('Create Your Account') }}</h4> 

                    @if(Session::has('error'))
                        <div class="alert alert-danger">
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    @if(Session::has('success'))
                        <div class="alert alert-success mb-4">
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif	
					
                    <form method="POST" class="form-signup" autocomplete="off" action="{{ route('register') }}">
                        @csrf

                        <div class="form-group row">
							<div class="col-lg-12">
                                <input id="name" type="text" placeholder="{{ _lang('Name') }}" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name" value="{{ old('name') }}" required autofocus>

                                @if ($errors->has('name'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">				
                            <div class="col-lg-12">
                                <div class="form-control py-0 pr-0 d-flex align-items-center parent-box">
                                    <span class="text-nowrap">{{ parse_url(url(''), PHP_URL_HOST).'/' }}</span>
                                    <input type="text" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}" name="workspace" value="{{ old('workspace', request()->workspace) }}" required>
                                    <div id="icon-box" class="mr-2"></div>
                                </div>
                                @if ($errors->has('workspace'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('workspace') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
				
						<div class="form-group row">
                            <div class="col-lg-12">
                                <input id="email" type="email" placeholder="{{ _lang('E-Mail Address') }}" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" required>

                                @if ($errors->has('email'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <select class="form-control{{ $errors->has('country_code') ? ' is-invalid' : '' }} select2" name="country_code" required>
                                    <option value="">{{ _lang('Country Code') }}</option>
                                    @foreach(get_country_codes() as $key => $value)
                                    <option value="{{ $value['dial_code'] }}" {{ old('country_code') == $value['dial_code'] ? 'selected' : '' }}>{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('country_code'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('country_code') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="col-lg-6">
                                <input id="mobile" type="text" placeholder="{{ _lang('Mobile') }}" class="form-control{{ $errors->has('mobile') ? ' is-invalid' : '' }}" name="mobile" value="{{ old('mobile') }}" required>

                                @if ($errors->has('mobile'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('mobile') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <input id="password" type="password" placeholder="{{ _lang('Password') }}" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" required autocomplete="new-password">
                            </div>

                            <div class="col-lg-6">
                                <input id="password-confirm" type="password" class="form-control" placeholder="{{ _lang('Confirm Password') }}" name="password_confirmation" required autocomplete="new-password">
                            </div>

                            @if ($errors->has('password'))
                            <div class="col-12 mt-2">
                                <span class="text-danger">
                                    <span>{{ $errors->first('password') }}</span>
                                </span>
                            </div>
                            @endif
                        </div>

                        <div class="form-group row">
                            <div class="col-lg-12">
                                <input type="hidden" class="{{ $errors->has('g-recaptcha-response') ? ' is-invalid' : '' }}" name="g-recaptcha-response" id="recaptcha">
                                @if($errors->has('g-recaptcha-response'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if(request()->package_id)
                        <input type="hidden" name="package_id" value="{{ request()->package_id }}">
                        @endif
						
						<div class="form-group row">
							<div class="col-lg-12 text-center">
								<button type="submit" class="btn btn-primary btn-block btn-login">
								{{ _lang('Create My Account') }}
                                </button>
							</div>
						</div>

                        <div class="form-group row mt-3">
							<div class="col-lg-12 text-center">
                               <a href="{{ route('login') }}">{{ _lang('Log In to your account') }}</a>
							</div>
						</div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@if(get_option('enable_recaptcha', 0) == 1)
<script src="https://www.google.com/recaptcha/api.js?render={{ get_option('recaptcha_site_key') }}"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ get_option('recaptcha_site_key') }}', {action: 'register'}).then(function(token) {
        if (token) {
            document.getElementById('recaptcha').value = token;
        }
        });
    });
</script>
@endif
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

                            $("#icon-box").html(`<i class="fas fa-exclamation-circle text-danger"></i>`);

                            if (!$inputField.parent().next('.error-msg').length) {
                                $inputField.parent().after(`<small class="text-danger error-msg"><i class="fas fa-exclamation-circle mr-1"></i>${response.message}</small>`);
                            }else{
                                $inputField.parent().next('.error-msg').html(`<i class="fas fa-exclamation-circle mr-1"></i>${response.message}`);
                            }
                        } else {
                            $inputField.parent().css("border", "1px solid #4f39f6");
                            $("#icon-box").html(`<i class="fas fa-check-circle text-success"></i>`);
                            $inputField.parent().next('.error-msg').remove();
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
