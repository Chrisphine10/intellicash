@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Total Tenants') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ $total_tenant }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="fas fa-users bg-primary text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Trial Tenants') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ $trial_tenant }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="fas fa-user-clock bg-info text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Paid Tenants') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ $paid_tenant }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="fas fa-user-shield bg-success text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Active Tenants') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ $active_tenant }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="fas fa-user-check bg-primary text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('New Tenants') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ $new_tenant }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="far fa-user bg-primary text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Expired Tenants') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ $expired_tenant }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="fas fa-user-times bg-danger text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Current Month Income') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($current_month_income, currency_symbol()) }}</b></h4>
					</div>
					<div class="ml-2 text-center">
						<i class="fas fa-dollar-sign bg-success text-white"></i>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<a href="{{ route('admin.subscription_payments.index') }}">
			<div class="card mb-4 dashboard-card">
				<div class="card-body">
					<div class="d-flex">
						<div class="flex-grow-1">
							<h5>{{ _lang('Offline Payment Request') }}</h5>
							<h4 class="pt-1 mb-0"><b>{{ $offline_payment_request }}</b></h4>
						</div>
						<div class="ml-2 text-center">
							<i class="fas fa-money-bill bg-dark text-white"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>
</div>

<div class="row">
	<div class="col-lg-6">
		<div class="card">
			<div class="card-header">
				<span>{{ _lang('Monthly Signup').' - '.date('Y') }}</span>
			</div>
			<div class="card-body">
				<h5 class="text-center loading-chart"><i class="fas fa-spinner fa-spin"></i> {{ _lang('Loading Chart') }}</h5>
				<canvas id="signUpAnalysis"></canvas>
			</div>
		</div>
	</div>

	<div class="col-lg-6">
		<div class="card">
			<div class="card-header">
				<span>{{ _lang('Subscription Payments').' - '.date('Y')  }}</span>
			</div>
			<div class="card-body">
				<h5 class="text-center loading-chart"><i class="fas fa-spinner fa-spin"></i> {{ _lang('Loading Chart') }}</h5>
				<canvas id="revenueAnalysis"></canvas>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-4">
		<div class="card">
			<div class="card-header">
				<span>{{ _lang('Package Wise Subscribed') }}</span>
			</div>
			<div class="card-body">
				<h5 class="text-center loading-chart"><i class="fas fa-spinner fa-spin"></i> {{ _lang('Loading Chart') }}</h5>
				<canvas id="packageOverview"></canvas>
			</div>
		</div>
	</div>

	<div class="col-lg-8">
		<div class="card mb-4">
			<div class="card-header">
				{{ _lang('Recent Tenants') }}
			</div>
			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table">
					<thead>
						<tr>
                            <th class="pl-4">{{ _lang('Date') }}</th>
                            <th>{{ _lang('Name') }}</th>
                            <th>{{ _lang('Package') }}</th>
                            <th>{{ _lang('Membership') }}</th>
                            <th>{{ _lang('Status') }}</th>
                            <th></th>
                        </tr>
					</thead>
					<tbody>
						@if($newTenants->count() == 0)
						<tr>
							<td colspan="5" class="text-center">{{ _lang('No Data Found !') }}</td>
						</tr>
						@endif
						@foreach($newTenants as $tenant)
						<tr>
                            <td class="pl-4">{{ $tenant->created_at }}</td>
                            <td>{{ $tenant->name }}</td>
                            <td>{{ $tenant->package->name }}</td>
                            <td>{{ ucwords($tenant->membership_type) }}</td>
                            <td>{!! xss_clean(status($tenant->status)) !!}</td>
							<td>
								<a href="{{ route('admin.tenants.show', $tenant->id) }}" class="btn btn-outline-primary btn-xs">{{ _lang('Details') }}</a>
							</td>
							</td>
                        </tr>
						@endforeach
					</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script src="{{ asset('public/backend/plugins/chartJs/chart.min.js') }}"></script>
<script src="{{ asset('public/backend/assets/js/dashboard-admin.js?v=1.0') }}"></script>
@endsection