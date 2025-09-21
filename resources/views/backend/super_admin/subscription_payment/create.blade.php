@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Add Offline Payment') }}</span>
			</div>
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="{{ route('admin.subscription_payments.store') }}" enctype="multipart/form-data">
					@csrf
					<div class="row">
						<div class="col-lg-12">
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Tenant') }}</label>						
								<div class="col-xl-9">
									<select class="form-control select2 auto-select" data-selected="{{ old('tenant_id') }}" name="tenant_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\Tenant::all() as $tenant)
										<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Payment Method') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ old('payment_method') }}" name="payment_method" value="{{ old('payment_method') }}" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\PaymentGateway::offline()->get() as $gateway)
										<option value="{{ $gateway->name }}">{{ $gateway->name }}</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Order / Transaction ID') }}</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control" name="order_id" value="{{ old('order_id') }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Subscription Plan') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select select2" data-selected="{{ old('package_id') }}" name="package_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\Package::active()->get() as $package)
                                        <option value="{{ $package->id }}">{{ $package->name }} ({{ decimalPlace($package->cost, currency_symbol()).'/'.ucwords($package->package_type) }})</option>
                                        @endforeach
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Amount') }} ({{ currency_symbol() }})</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control float-field" name="amount" value="{{ old('amount') }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ old('status', 1) }}" name="status" required>
										<option value="1">{{ _lang('Completed') }}</option>
										<option value="0">{{ _lang('Pending') }}</option>						
									</select>
								</div>
							</div>
						
							<div class="form-group row mt-2">
                                <div class="col-xl-9 offset-xl-3">
                                    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Submit') }}</button>
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


