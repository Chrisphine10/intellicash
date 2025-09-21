@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Payment Gateways') }}</span>
			</div>
			<div class="card-body p-0">
				<table class="table table-striped">
					<thead>
						<th class="pl-4">{{ _lang('Name') }}</th>
						<th class="text-center">{{ _lang('Status') }}</th>
						<th class="text-center">{{ _lang('Action') }}</th>
					</thead>
					<tbody>
					@foreach($paymentgateways as $paymentgateway)
						<tr>
							<td class="pl-4">
								<div class="d-flex align-items-center">
									<img src="{{ asset('public/backend/images/gateways/'.$paymentgateway->image) }}" class="thumb-sm img-thumbnail rounded-circle mr-3">
									<div><span class="d-block text-height-0"><b>{{ $paymentgateway->name }}</b></span></div>
								</div>
							</td>
							<td class="text-center">{!! xss_clean(status($paymentgateway->status)) !!}</td>
							<td class="text-center">
								<a href="{{ route('admin.payment_gateways.edit', $paymentgateway->id) }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-2"></i>{{ _lang('Config') }}</a>
							</td>
						</tr>
					@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection