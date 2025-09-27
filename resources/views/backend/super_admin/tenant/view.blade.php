@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<ul class="nav nav-tabs business-settings-tabs" role="tablist">
			 <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#overview"><i class="far fa-user-circle mr-2"></i><span>{{ _lang('Tenant Details') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#package_details"><i class="fas fa-box mr-2"></i><span>{{ _lang('Subscription Plan') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#payments"><i class="far fa-credit-card mr-2"></i><span>{{ _lang('Payments') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#email"><i class="fas fa-at mr-2"></i><span>{{ _lang('Send Email') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" href="{{ route('admin.tenants.edit', $tenant->id) }}"><i class="far fa-edit mr-2"></i><span>{{ _lang('Edit Tenant') }}</span></a></li>
		</ul>

		<div class="tab-content settings-tab-content">
		
			<div id="overview" class="tab-pane active">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Tenant Details') }}</span>
					</div>
					
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr><td>{{ _lang('Name') }}</td><td>{{ $tenant->name }}</td></tr>
								<tr><td>{{ _lang('Email') }}</td><td>{{ $tenant->owner ? $tenant->owner->email : _lang('Not Available') }}</td></tr>
								<tr>
									<td>{{ _lang('Workspace') }}</td>
									<td>{{ $tenant->slug }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Login URL') }}</td>
									<td>{{ url('/'.$tenant->slug.'/login') }}</td>
								</tr>
								<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(status($tenant->status)) !!}</td></tr>
								<tr><td>{{ _lang('Membership Type') }}</td><td>{{ ucwords($tenant->membership_type) }}</td></tr>
								<tr><td>{{ _lang('Subscription Date') }}</td><td>{{ $tenant->subscription_date }}</td></tr>
								<tr><td>{{ _lang('Expiration') }}</td><td>{{ $tenant->valid_to }}</td></tr>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div id="package_details" class="tab-pane">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Package Details') }}</span>
					</div>
					
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr>
									<td>{{ _lang('Subscription Plan') }}</td>
									<td>{{ $tenant->package->name }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Plan Type') }}</td>
									<td>{{ ucwords($tenant->package->package_type) }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Role Based User Limit') }}</td>
									<td>{{ str_replace('-1',_lang('Unlimited'), $tenant->package->user_limit) }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Member Limit') }}</td>
									<td>{{ str_replace('-1',_lang('Unlimited'), $tenant->package->member_limit) }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Branch Limit') }}</td>
									<td>{{ str_replace('-1',_lang('Unlimited'), $tenant->package->branch_limit) }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Account Type Limit') }}</td>
									<td>{{ str_replace('-1',_lang('Unlimited'), $tenant->package->account_type_limit) }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Account Limit') }}</td>
									<td>{{ str_replace('-1',_lang('Unlimited'), $tenant->package->account_limit) }}</td>
								</tr>
								<tr>
									<td>{{ _lang('Member Portal') }}</td>
									<td>{{ $tenant->package->member_portal == 1 ? _lang('Yes') : _lang('No') }}</td>
								</tr>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div id="payments" class="tab-pane">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Payments') }}</span>
					</div>
					
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-bordered mb-0">
								<thead>
									<tr>
										<th class="pl-4">{{ _lang('Order ID') }}</th>
										<th>{{ _lang('Payment Date') }}</th>
										<th>{{ _lang('Amount') }}</th>
										<th>{{ _lang('Payment Method') }}</th>
										<th>{{ _lang('Status') }}</th>
									</tr>
								</thead>
								<tbody>
									@foreach($tenant->subscriptionPayments as $payment)
										<tr>
											<td class="pl-4">{{ $payment->order_id }}</td>
											<td>{{ $payment->created_at }}</td>
											<td>{{ decimalPlace($payment->amount, currency_symbol()) }}</td>
											<td>{{ $payment->payment_method }}</td>
											<td>
												@if ($payment->status == 0)
													{!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
												@elseif ($payment->status == 1)
													{!! xss_clean(show_status(_lang('Completed'), 'success')) !!}
												@elseif ($payment->status == 2)
													{!! xss_clean(show_status(_lang('Hold'), 'primary')) !!}
												@elseif ($payment->status == 3)
													{!! xss_clean(show_status(_lang('Refund'), 'info')) !!}
												@elseif ($payment->status == 4)
													{!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
												@endif
											</td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div id="email" class="tab-pane">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Send Email') }}</span>
					</div>
					
					<div class="card-body">
						<form method="post" class="validate" autocomplete="off" action="{{ route('admin.tenants.send_email') }}">
							@csrf
							<input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
							
							<div class="form-group{{ $errors->has('subject') ? ' has-error' : '' }}">
								<label class="control-label">{{ _lang('Subject') }}</label>
								<input type="text" class="form-control" name="subject" value="{{ old('subject') }}" required>
							</div>

							<div class="form-group{{ $errors->has('message') ? ' has-error' : '' }}">
								<label class="control-label">{{ _lang('Message') }}</label>
								<textarea class="form-control mini-summernote" name="message">{{ old('message') }}</textarea>
							</div>

							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-2"></i>{{ _lang('Send Email') }}</button>
							</div>
						</form>
					</div>
				</div>
			</div>
	    </div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function($) {
    "use strict";
	
	function getQueryParam(name) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    function updateQueryParam(tabName) {
        var newUrl = window.location.pathname + "?tab=" + tabName;
        history.replaceState(null, null, newUrl);
    }

    // Get tab name from query string
    var tabName = getQueryParam("tab");

    if (tabName) {
        var $tabLink = $('.nav-tabs a[href="#' + tabName + '"]');
        if ($tabLink.length) {
            $tabLink.tab("show");
        }
    } else {
        $('.nav-tabs a:first').tab("show");
    }

    // Update query string on tab click
    $(".nav-tabs [data-toggle='tab']").on("click", function (e) {
        e.preventDefault();
        $(this).tab("show");

        var tabId = $(this).attr("href").replace("#", "");
        updateQueryParam(tabId);
    });
})(jQuery);
</script>
@endsection