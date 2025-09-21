@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Update Offline Payment') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('admin.subscription_payments.update', $id) }}" enctype="multipart/form-data">
					@csrf
					<input name="_method" type="hidden" value="PATCH">

					<div class="row">
						<div class="col-lg-12">
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Tenant') }}</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control" name="tenant_id" value="{{ $subscriptionpayment->tenant->name }}" readonly>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Payment Method') }}</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control" name="payment_method" value="{{ $subscriptionpayment->payment_method }}" readonly>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Order / Transaction ID') }}</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control" name="order_id" value="{{ $subscriptionpayment->order_id }}" readonly>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Subscription Plan') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select select2" data-selected="{{ $subscriptionpayment->package_id }}" name="package_id" required>
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
									<input type="text" class="form-control float-field" name="amount" value="{{ $subscriptionpayment->amount }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ $subscriptionpayment->status }}" name="status" required>
										<option value="0">{{ _lang('Pending') }}</option>		
										<option value="1">{{ _lang('Completed') }}</option>		
										<option value="2">{{ _lang('Hold') }}</option>						
										<option value="3">{{ _lang('Refunded') }}</option>						
										<option value="4">{{ _lang('Cancelled') }}</option>						
									</select>
									<small class="text-primary"><i class="fas fa-info-circle mr-1"></i>{{ _lang('No existing action will be reverted based on the status change') }}</small>
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


