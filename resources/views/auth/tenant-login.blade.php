@extends('layouts.auth')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-signin p-3 my-5">
                <div class="card-body">
					<img class="logo" src="{{ get_logo() }}">

					<h5 class="text-center py-4">{{ _lang('LOGIN TO YOUR ACCOUNT') }}</h5>

                    @if(Session::has('error'))
                        <div class="alert alert-danger text-center">
                            <strong>{{ session('error') }}</strong>
                        </div>
                    @endif

                    @if(Session::has('success'))
                        <div class="alert alert-success text-center">
                            <strong>{{ session('success') }}</strong>
                        </div>
                    @endif

					@if(Session::has('registration_success'))
                        <div class="alert alert-success text-center">
                            <strong>{{ session('registration_success') }}</strong>
                        </div>
                    @endif

					<form method="POST" class="form-signin" action="{{ url('/login') }}">
                        @csrf
                        <div class="form-group row">
                            <div class="col-md-12">
                                <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="{{ _lang('Email') }}" required autofocus>

                                @if ($errors->has('email'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row mb-0">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary btn-block">
                                    {{ _lang('Next') }}
                                </button>

                                @if(get_option('member_signup') == 1)
                                    <a href="{{ route('register') }}" class="btn btn-link btn-register">{{ _lang('Create an Account') }}</a>
								@endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
