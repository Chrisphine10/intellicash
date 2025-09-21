@extends('layouts.guest')

@section('content')
<div class="row">
    <div class="col-lg-6 offset-lg-3">
        <div class="card">
            <div class="card-header text-center">
                <span class="panel-title text-danger">{{ _lang('Subscription Expired') }}</span>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <h4>{{ _lang('Your subscription has been expired') }}</h4>
                    <p>{{ _lang('Please renew your subscription to continue using our services') }}</p>
                    <a href="{{ route('membership.payment_gateways') }}" class="btn btn-primary mt-3">{{ _lang('Renew Subscription') }}<i class="fas fa-arrow-right ml-2"></i></a>
                    <a href="{{ route('logout') }}" class="btn btn-danger mt-3"><i class="fas fa-sign-out-alt mr-2"></i>{{ _lang('Logout') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection