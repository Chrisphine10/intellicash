@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header text-center">
				<span class="header-title">{{ _lang('Online Gateways') }}</span>
			</div>
			<div class="card-body">
				<div class="row">
					@if($paymentGateways->count() == 0)
						<div class="col-lg-12">
							<h5 class="text-center py-5">
								<i class="fas fa-info-circle"></i> {{ _lang('No Payment Gateway Found !') }}
							</h5>
						</div>
					@endif

					@foreach($paymentGateways as $paymentgateway)
					<div class="col-lg-4 mb-4">
						<div class="card text-center">
							<div class="card-body">
								<img class="thumb-xl m-auto rounded-circle img-thumbnail" src="{{ asset('public/backend/images/gateways/'.$paymentgateway->image) }}"/>
								<h6 class="mt-3 mb-2">{{ $paymentgateway->name }}</h6>
								<p class="mb-2">{!! xss_clean(status($paymentgateway->status)) !!}</p>
								<a href="{{ route('automatic_methods.edit', $paymentgateway->id) }}" class="btn btn-light btn-block btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Config') }}</a>
							</div>
						</div>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>
@endsection