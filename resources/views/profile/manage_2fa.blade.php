@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-xl-4 col-lg-6 offset-xl-4 offset-lg-3">
		<div class="card">
			<div class="card-header panel-title text-center">
				@if(isset($qrCodeUrl))
				{{ _lang('Enable 2FA Authentication') }}
                @else
                {{ _lang('Disable 2FA Authentication') }}
                @endif
			</div>

			<div class="card-body text-center">
                @if(isset($qrCodeUrl))
                    <p class="mb-4"><strong>{{ _lang('Scan the QR code bellow with the google authenticator application') }}</strong></p>
                    {!! QrCode::size(250)->generate($qrCodeUrl) !!}
                @else
                    <p class="mb-4"><strong>{{ _lang('Enter the 2FA code from your authenticator app') }}</strong></p>
                @endif
                <form method="POST" class="validate mt-4" action="{{ $actionUrl }}" autocomplete="off">
                    @csrf
                    <div class="form-group row">
                        <div class="col-md-12">
                            <input type="text" class="form-control{{ $errors->has('one_time_password') ? ' is-invalid' : '' }}" name="one_time_password" value="{{ old('one_time_password') }}" placeholder="{{ _lang('One Time Password') }}" required autofocus>

                            @if ($errors->has('one_time_password'))
                                <span class="invalid-feedback">
                                    <strong>{{ $errors->first('one_time_password') }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary btn-block">
                                {{ _lang('CONTINUE') }}
                            </button>						
                        </div>
                    </div>
                </form>
			</div>
		</div>
	</div>
</div>
@endsection