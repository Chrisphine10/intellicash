@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Withdraw Methods') }}</h4>
			</div>
			<div class="card-body">
                @if($tenantPaymentMethods->count() > 0)
                <h5 class="text-primary mb-4">{{ _lang('Tenant Payment Methods') }}</h5>
                <div class="row justify-content-md-center mb-5">
                    @foreach($tenantPaymentMethods as $paymentMethod)
                    <div class="col-md-4">
                        <div class="card mb-4 border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-credit-card fa-3x text-primary mb-3"></i>
                                <h5 class="mt-3"><b>{{ $paymentMethod['name'] }}</b></h5>
                                <p class="text-muted">{{ _lang('Available Balance') }}: {{ decimalPlace($paymentMethod['available_balance'], currency($paymentMethod['currency'])) }}</p>
                                <a href="{{ route('withdraw.manual_withdraw', 'payment_' . $paymentMethod['bank_account']->id) }}" 
                                   class="btn btn-primary mt-3 stretched-link">
                                   {{ _lang('Withdraw via') }} {{ ucfirst($paymentMethod['bank_account']->payment_method_type) }}
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($withdraw_methods->count() > 0)
                <h5 class="text-primary mb-4">{{ _lang('Traditional Withdrawal Methods') }}</h5>
                <div class="row justify-content-md-center">
                    @foreach($withdraw_methods as $withdraw_method)
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <img src="{{ asset('public/uploads/media/'.$withdraw_method->image) }}" class="thumb-xl m-auto rounded-circle img-thumbnail"/>
                                <h5 class="mt-3"><b>{{ $withdraw_method->name }}</b></h5>
                                <a href="{{ route('withdraw.manual_withdraw',$withdraw_method->id) }}" data-title="{{ _lang('Withdraw Via').' '.$withdraw_method->name }}" class="btn btn-secondary mt-3 stretched-link">{{ _lang('Make Withdraw') }}</a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($withdraw_methods->count() == 0 && $tenantPaymentMethods->count() == 0)
                <div class="text-center py-4">
                    <i class="fas fa-money-check fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">{{ _lang('No withdrawal methods available') }}</h5>
                    <p class="text-muted">{{ _lang('Please contact your administrator to set up withdrawal methods') }}</p>
                </div>
                @endif
			</div>
		</div>
    </div>
</div>
@endsection