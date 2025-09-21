@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
		    <div class="card-header">
				<span class="panel-title">{{ _lang('Package Details') }}</span>
			</div>
			
			<div class="card-body">
			    <table class="table table-bordered">
				    <tr><td>{{ _lang('Pasckage Name') }}</td><td>{{ $package->name }}</td></tr>
				    <tr><td>{{ _lang('Package Type') }}</td><td>{{ ucwords($package->package_type) }}</td></tr>
					<tr><td>{{ _lang('Cost') }}</td><td>{{ decimalPlace($package->cost, currency_symbol()) }}</td></tr>
					<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(status($package->status)) !!}</td></tr>
					<tr>
						<td>{{ _lang('Is Popular') }}</td>
						<td>
							@if($package->is_popular == 1)
							{!! xss_clean(show_status(_lang('Yes'), 'success')) !!}
							@else
							{!! xss_clean(show_status(_lang('No'), 'danger')) !!}
							@endif
						</td>
					</tr>
					<tr><td>{{ _lang('Discount') }}</td><td>{{ $package->discount }} %</td></tr>
					<tr><td>{{ _lang('Trial Days') }}</td><td>{{ $package->trial_days }}</td></tr>
					<tr><td>{{ _lang('Role Based Users') }}</td><td>{{ $package->user_limit != '-1' ? $package->user_limit : _lang('Unlimited') }}</td></tr>
					<tr><td>{{ _lang('Member Limit') }}</td><td>{{ $package->member_limit != '-1' ? $package->member_limit : _lang('Unlimited') }}</td></tr>
					<tr><td>{{ _lang('Branch Limit') }}</td><td>{{ $package->branch_limit != '-1' ? $package->branch_limit : _lang('Unlimited') }}</td></tr>
					<tr><td>{{ _lang('Account Type Limit') }}</td><td>{{ $package->account_type_limit != '-1' ? $package->account_type_limit : _lang('Unlimited') }}</td></tr>
					<tr><td>{{ _lang('Account Limit') }}</td><td>{{ $package->account_limit != '-1' ? $package->account_limit : _lang('Unlimited') }}</td></tr>
					<tr>
						<td>{{ _lang('Member Portal') }}</td>
						<td>
							@if($package->member_portal == 1)
							{!! xss_clean(show_status(_lang('Yes'), 'success')) !!}
							@else
							{!! xss_clean(show_status(_lang('No'), 'danger')) !!}
							@endif
						</td>
					</tr>
			    </table>
			</div>
	    </div>
	</div>
</div>
@endsection


