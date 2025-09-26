@extends('layouts.app')

@section('content')
<!-- Date Range Filter -->
<div class="row mb-4">
	<div class="col-12">
		<div class="card">
			<div class="card-body">
				<div class="row align-items-center">
					<div class="col-md-6">
						<h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i>{{ _lang('Analytics Dashboard') }}</h5>
						<small class="text-muted">{{ $date_range['label'] }} - {{ date('M d, Y', strtotime($date_range['start'])) }} to {{ date('M d, Y', strtotime($date_range['end'])) }}</small>
					</div>
					<div class="col-md-6 text-right">
						<form method="GET" class="d-inline-block">
							<div class="form-group mb-0">
								<select name="range" class="form-control" onchange="this.form.submit()" style="width: 200px;">
									<option value="today" {{ request('range') == 'today' ? 'selected' : '' }}>{{ _lang('Today') }}</option>
									<option value="yesterday" {{ request('range') == 'yesterday' ? 'selected' : '' }}>{{ _lang('Yesterday') }}</option>
									<option value="last_7_days" {{ request('range') == 'last_7_days' ? 'selected' : '' }}>{{ _lang('Last 7 Days') }}</option>
									<option value="last_30_days" {{ request('range') == 'last_30_days' ? 'selected' : '' }}>{{ _lang('Last 30 Days') }}</option>
									<option value="this_month" {{ request('range') == 'this_month' || !request('range') ? 'selected' : '' }}>{{ _lang('This Month') }}</option>
									<option value="last_month" {{ request('range') == 'last_month' ? 'selected' : '' }}>{{ _lang('Last Month') }}</option>
									<option value="this_year" {{ request('range') == 'this_year' ? 'selected' : '' }}>{{ _lang('This Year') }}</option>
								</select>
							</div>
						</form>
						<button class="btn btn-outline-primary ml-2" onclick="refreshDashboard()">
							<i class="fas fa-sync-alt"></i> {{ _lang('Refresh') }}
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Key Metrics Cards -->
<div class="row">
	<div class="col-xl-3 col-md-6">
		<a href="{{ route('members.index') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card members-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Total Members') }}</div>
							<div class="card-value">{{ $total_customer }}</div>
							<div class="card-subtitle">
								<i class="fas fa-arrow-up text-success"></i> 
								<span class="text-success">{{ $member_growth['growth'] }}%</span> 
								{{ _lang('vs last period') }}
							</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-users"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>

	<div class="col-xl-3 col-md-6">
		<a href="{{ route('deposit_requests.index') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card active-members-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Active Members') }}</div>
							<div class="card-value">{{ $active_members }}</div>
							<div class="card-subtitle">
								<span class="text-info">{{ round(($active_members / max($total_customer, 1)) * 100, 1) }}%</span> 
								{{ _lang('of total') }}
							</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-user-check"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>

	<div class="col-xl-3 col-md-6">
		<a href="{{ route('loans.filter', 'active') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card loans-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Active Loans') }}</div>
							<div class="card-value">{{ $active_loans }}</div>
							<div class="card-subtitle">
								<span class="text-warning">{{ $overdue_loans }}</span> 
								{{ _lang('overdue') }}
							</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-hand-holding-usd"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>

	<div class="col-xl-3 col-md-6">
		<a href="{{ route('transactions.index') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card revenue-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Monthly Revenue') }}</div>
							<div class="card-value">{{ decimalPlace($monthly_revenue, currency()) }}</div>
							<div class="card-subtitle">
								<i class="fas fa-chart-line text-success"></i> 
								{{ _lang('This period') }}
							</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-dollar-sign"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>
</div>

<!-- Secondary Metrics Row -->
<div class="row">
	<div class="col-xl-2 col-md-4 col-6">
		<div class="card mb-4 dashboard-card modern-card secondary-card">
			<div class="card-body text-center">
				<div class="secondary-icon">
					<i class="fas fa-list-alt"></i>
				</div>
				<div class="secondary-title">{{ _lang('Total Loans') }}</div>
				<div class="secondary-value">{{ $total_loans }}</div>
			</div>
		</div>
	</div>
	<div class="col-xl-2 col-md-4 col-6">
		<div class="card mb-4 dashboard-card modern-card secondary-card">
			<div class="card-body text-center">
				<div class="secondary-icon warning">
					<i class="fas fa-clock"></i>
				</div>
				<div class="secondary-title">{{ _lang('Pending') }}</div>
				<div class="secondary-value text-warning">{{ $pending_loans }}</div>
			</div>
		</div>
	</div>
	<div class="col-xl-2 col-md-4 col-6">
		<div class="card mb-4 dashboard-card modern-card secondary-card">
			<div class="card-body text-center">
				<div class="secondary-icon success">
					<i class="fas fa-check-circle"></i>
				</div>
				<div class="secondary-title">{{ _lang('Completed') }}</div>
				<div class="secondary-value text-success">{{ $loan_performance['completed'] }}</div>
			</div>
		</div>
	</div>
	<div class="col-xl-2 col-md-4 col-6">
		<div class="card mb-4 dashboard-card modern-card secondary-card">
			<div class="card-body text-center">
				<div class="secondary-icon info">
					<i class="fas fa-percentage"></i>
				</div>
				<div class="secondary-title">{{ _lang('Success Rate') }}</div>
				<div class="secondary-value text-info">{{ $loan_performance['success_rate'] }}%</div>
			</div>
		</div>
	</div>
	<div class="col-xl-2 col-md-4 col-6">
		<div class="card mb-4 dashboard-card modern-card secondary-card">
			<div class="card-body text-center">
				<div class="secondary-icon">
					<i class="fas fa-exchange-alt"></i>
				</div>
				<div class="secondary-title">{{ _lang('Total Transactions') }}</div>
				<div class="secondary-value">{{ $total_transactions }}</div>
			</div>
		</div>
	</div>
	<div class="col-xl-2 col-md-4 col-6">
		<div class="card mb-4 dashboard-card modern-card secondary-card">
			<div class="card-body text-center">
				<div class="secondary-icon danger">
					<i class="fas fa-exclamation-triangle"></i>
				</div>
				<div class="secondary-title">{{ _lang('Default Rate') }}</div>
				<div class="secondary-value text-danger">{{ $loan_performance['default_rate'] }}%</div>
			</div>
		</div>
	</div>
</div>

<!-- Asset and Employee Summary (if available) -->
@if($asset_summary || $employee_summary)
<div class="row">
	@if($asset_summary)
	<div class="col-md-6">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-building mr-2"></i>{{ _lang('Asset Summary') }}</h5>
			</div>
			<div class="card-body">
				<div class="row text-center">
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Assets') }}</h6>
						<h4>{{ $asset_summary['total'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Value') }}</h6>
						<h4>{{ decimalPlace($asset_summary['total_value'], currency()) }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Active') }}</h6>
						<h4 class="text-success">{{ $asset_summary['active'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Leasable') }}</h6>
						<h4 class="text-info">{{ $asset_summary['leasable'] }}</h4>
					</div>
				</div>
			</div>
		</div>
	</div>
	@endif
	
	@if($employee_summary)
	<div class="col-md-6">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-users-cog mr-2"></i>{{ _lang('Employee Summary') }}</h5>
			</div>
			<div class="card-body">
				<div class="row text-center">
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Employees') }}</h6>
						<h4>{{ $employee_summary['total'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Active') }}</h6>
						<h4 class="text-success">{{ $employee_summary['active'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Departments') }}</h6>
						<h4>{{ count($employee_summary['departments']) }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Employment Types') }}</h6>
						<h4>{{ count($employee_summary['employment_types']) }}</h4>
					</div>
				</div>
			</div>
		</div>
	</div>
	@endif
	
	@if($vsla_summary)
	<div class="col-md-6">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-handshake mr-2"></i>{{ _lang('VSLA Summary') }}</h5>
			</div>
			<div class="card-body">
				<div class="row text-center">
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Transactions') }}</h6>
						<h4>{{ $vsla_summary['total_transactions'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Approved') }}</h6>
						<h4 class="text-success">{{ $vsla_summary['approved_transactions'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Shares') }}</h6>
						<h4 class="text-info">{{ decimalPlace($vsla_summary['total_shares'], currency()) }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Welfare Fund') }}</h6>
						<h4 class="text-warning">{{ decimalPlace($vsla_summary['total_welfare'], currency()) }}</h4>
					</div>
				</div>
			</div>
		</div>
	</div>
	@endif
	
	@if($voting_summary)
	<div class="col-md-6">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-vote-yea mr-2"></i>{{ _lang('Voting Summary') }}</h5>
			</div>
			<div class="card-body">
				<div class="row text-center">
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Positions') }}</h6>
						<h4>{{ $voting_summary['total_positions'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Active Positions') }}</h6>
						<h4 class="text-success">{{ $voting_summary['active_positions'] }}</h4>
					</div>
					<div class="col-12">
						<h6 class="text-muted">{{ _lang('Total Votes Cast') }}</h6>
						<h4 class="text-primary">{{ $voting_summary['total_votes_cast'] }}</h4>
					</div>
				</div>
			</div>
		</div>
	</div>
	@endif
	
	@if($esignature_summary)
	<div class="col-md-6">
		<div class="card mb-4">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-signature mr-2"></i>{{ _lang('E-Signature Summary') }}</h5>
			</div>
			<div class="card-body">
				<div class="row text-center">
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Total Documents') }}</h6>
						<h4>{{ $esignature_summary['total_documents'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Completed') }}</h6>
						<h4 class="text-success">{{ $esignature_summary['completed_documents'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Pending') }}</h6>
						<h4 class="text-warning">{{ $esignature_summary['pending_documents'] }}</h4>
					</div>
					<div class="col-6">
						<h6 class="text-muted">{{ _lang('Cancelled') }}</h6>
						<h4 class="text-danger">{{ $esignature_summary['cancelled_documents'] }}</h4>
					</div>
				</div>
			</div>
		</div>
	</div>
	@endif
</div>
@endif

<!-- Analytics Charts Section -->
<div class="row">
	<!-- Member Growth Chart -->
	<div class="col-lg-6 mb-4">
		<div class="card">
			<div class="card-header d-flex align-items-center justify-content-between">
				<span><i class="fas fa-chart-area mr-2"></i>{{ _lang('Member Growth') }}</span>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-primary active" data-chart="member_growth">Growth</button>
					<button type="button" class="btn btn-outline-primary" data-chart="loan_performance">Loans</button>
				</div>
			</div>
			<div class="card-body">
				<canvas id="memberGrowthChart" height="300"></canvas>
			</div>
		</div>
	</div>

	<!-- Transaction Trends Chart -->
	<div class="col-lg-6 mb-4">
		<div class="card">
			<div class="card-header d-flex align-items-center justify-content-between">
				<span><i class="fas fa-chart-line mr-2"></i>{{ _lang('Transaction Trends') }}</span>
				<div class="btn-group btn-group-sm" role="group">
					<button type="button" class="btn btn-outline-primary active" data-chart="transaction_trends">Trends</button>
					<button type="button" class="btn btn-outline-primary" data-chart="revenue_breakdown">Revenue</button>
				</div>
			</div>
			<div class="card-body">
				<canvas id="transactionTrendsChart" height="300"></canvas>
			</div>
		</div>
	</div>
</div>

<!-- Secondary Charts Row -->
<div class="row">
	<!-- Expense Overview -->
	<div class="col-lg-4 mb-4">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span><i class="fas fa-chart-pie mr-2"></i>{{ _lang('Expense Overview') }}</span>
			</div>
			<div class="card-body">
				<canvas id="expenseOverview" height="300"></canvas>
			</div>
		</div>
	</div>

	<!-- Deposit & Withdraw Analytics -->
	<div class="col-lg-8 mb-4">
		<div class="card">
			<div class="card-header d-flex align-items-center justify-content-between">
				<span><i class="fas fa-chart-bar mr-2"></i>{{ _lang('Deposit & Withdraw Analytics') }}</span>
				<select class="filter-select ml-auto py-0 auto-select" data-selected="{{ base_currency_id() }}">
					@foreach(\App\Models\Currency::where('status',1)->get() as $currency)
					<option value="{{ $currency->id }}" data-symbol="{{ currency_symbol($currency->name) }}">{{ $currency->name }}</option>
					@endforeach
				</select>
			</div>
			<div class="card-body">
				<canvas id="transactionAnalysis" height="300"></canvas>
			</div>
		</div>
	</div>
</div>

<!-- Performance Metrics Row -->
<div class="row">
	<!-- Top Members -->
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-trophy mr-2"></i>{{ _lang('Top Performing Members') }}</h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm">
						<thead>
							<tr>
								<th>{{ _lang('Member') }}</th>
								<th>{{ _lang('Transactions') }}</th>
								<th class="text-right">{{ _lang('Total Deposits') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($top_members->take(5) as $member)
							<tr>
								<td>
									<div class="d-flex align-items-center">
										<div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2">
											{{ strtoupper(substr($member->first_name, 0, 1)) }}
										</div>
										<div>
											<h6 class="mb-0">{{ $member->first_name }} {{ $member->last_name }}</h6>
											<small class="text-muted">{{ $member->member_no }}</small>
										</div>
									</div>
								</td>
								<td><span class="badge badge-info">{{ $member->transactions_count }}</span></td>
								<td class="text-right font-weight-bold">{{ decimalPlace($member->total_deposits ?? 0, currency()) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Branch Performance -->
	<div class="col-lg-6 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-building mr-2"></i>{{ _lang('Branch Performance') }}</h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-sm">
						<thead>
							<tr>
								<th>{{ _lang('Branch') }}</th>
								<th>{{ _lang('New Members') }}</th>
								<th class="text-right">{{ _lang('Transactions') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($branch_performance->take(5) as $branch)
							<tr>
								<td>
									<h6 class="mb-0">{{ $branch->name }}</h6>
								</td>
								<td><span class="badge badge-success">{{ $branch->members_count }}</span></td>
								<td class="text-right font-weight-bold">{{ decimalPlace($branch->total_transactions ?? 0, currency()) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Currency Breakdown -->
<div class="row">
	<div class="col-12 mb-4">
		<div class="card">
			<div class="card-header">
				<h5 class="mb-0"><i class="fas fa-coins mr-2"></i>{{ _lang('Currency Breakdown') }}</h5>
			</div>
			<div class="card-body">
				<div class="row">
					@foreach($currency_breakdown as $currency)
					<div class="col-md-3 col-sm-6 mb-3">
						<div class="card border-left-primary">
							<div class="card-body">
								<div class="d-flex align-items-center">
									<div class="flex-grow-1">
										<h6 class="text-muted mb-1">{{ $currency->name }}</h6>
										<h4 class="mb-0">{{ $currency->transactions_count }}</h4>
										<small class="text-success">{{ _lang('transactions') }}</small>
									</div>
									<div class="text-right">
										<h5 class="text-primary mb-0">{{ decimalPlace($currency->total_amount ?? 0, currency_symbol($currency->name)) }}</h5>
									</div>
								</div>
							</div>
						</div>
					</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12 mb-4">
		<div class="card mb-4">
			<div class="card-header">
				{{ _lang('Active Loan Balances') }}
			</div>
			<div class="card-body px-0 pt-0">
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th class="text-nowrap pl-4">{{ _lang('Currency') }}</th>
								<th class="text-nowrap">{{ _lang('Applied Amount') }}</th>
								<th class="text-nowrap">{{ _lang('Paid Amount') }}</th>
								<th class="text-nowrap">{{ _lang('Due Amount') }}</th>
							</tr>
						</thead>
						<tbody>
							@if(count($loan_balances) == 0)
								<tr>
									<td colspan="4"><p class="text-center">{{ _lang('No Data Available') }}</p></td>
								</tr>
							@endif
							@foreach($loan_balances as $loan_balance)
							<tr>
								<td class="pl-4">{{ $loan_balance->currency->name }}</td>
								<td>{{ decimalPlace($loan_balance->total_amount, currency($loan_balance->currency->name)) }}</td>
								<td>{{ decimalPlace($loan_balance->total_paid, currency($loan_balance->currency->name)) }}</td>
								<td>{{ decimalPlace($loan_balance->total_amount - $loan_balance->total_paid, currency($loan_balance->currency->name)) }}</td>
							</tr>
							@endforeach	
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>


<div class="row">
	<div class="col-md-12 mb-4">
		<div class="card mb-4">
			<div class="card-header">
				{{ _lang('Due Loan Payments') }}
			</div>
			<div class="card-body px-0 pt-0">
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th class="text-nowrap pl-4">{{ _lang('Loan ID') }}</th>
								<th class="text-nowrap">{{ _lang('Member No') }}</th>
								<th class="text-nowrap">{{ _lang('Member') }}</th>
								<th class="text-nowrap">{{ _lang('Last Payment Date') }}</th>
								<th class="text-nowrap">{{ _lang('Due Repayments') }}</th>
								<th class="text-nowrap text-right pr-4">{{ _lang('Total Due') }}</th>
							</tr>
						</thead>
						<tbody>
							@if(count($due_repayments) == 0)
								<tr>
									<td colspan="6"><p class="text-center">{{ _lang('No Data Available') }}</p></td>
								</tr>
							@endif

							@foreach($due_repayments as $repayment)
							<tr>
								<td class="pl-4">{{ $repayment->loan->loan_id }}</td>
								<td>{{ $repayment->loan->borrower->member_no }}</td>
								<td>{{ $repayment->loan->borrower->name }}</td>
								<td class="text-nowrap">{{ $repayment->repayment_date }}</td>
								<td class="text-nowrap">{{ $repayment->total_due_repayment }}</td>
								<td class="text-nowrap text-right pr-4">{{ decimalPlace($repayment->total_due, currency($repayment->loan->currency->name)) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-lg-12">
		<div class="card mb-4">
			<div class="card-header">
				{{ _lang('Recent Transactions') }}
			</div>
			<div class="card-body px-0 pt-0">
				<div class="table-responsive">
					<table class="table table-bordered">
					<thead>
					    <tr>
						    <th class="pl-4">{{ _lang('Date') }}</th>
							<th>{{ _lang('Member') }}</th>
							<th class="text-nowrap">{{ _lang('Account Number') }}</th>
							<th>{{ _lang('Amount') }}</th>
							<th class="text-nowrap">{{ _lang('Debit/Credit') }}</th>
							<th>{{ _lang('Type') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					@if(count($recent_transactions) == 0)
						<tr>
							<td colspan="8"><p class="text-center">{{ _lang('No Data Available') }}</p></td>
						</tr>
					@endif
					@foreach($recent_transactions as $transaction)
						@php
						$symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
						$class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
						@endphp
						<tr>
							<td class="text-nowrap pl-4">{{ $transaction->trans_date }}</td>
							<td>{{ $transaction->member->name }}</td>
							<td>{{ $transaction->account->account_number }}</td>
							<td><span class="text-nowrap {{ $class }}">{{ $symbol.' '.decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</span></td>
							<td>{{ strtoupper($transaction->dr_cr) }}</td>
							<td>{{ ucwords(str_replace('_',' ',$transaction->type)) }}</td>
							<td>{!! xss_clean(transaction_status($transaction->status)) !!}</td>
							<td class="text-center"><a href="{{ route('transactions.show', $transaction->id) }}" target="_blank" class="btn btn-outline-primary btn-xs"><i class="ti-arrow-right"></i>&nbsp;{{ _lang('View') }}</a></td>
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
<script src="{{ asset('public/backend/assets/js/dashboard.js') }}"></script>
<script>
$(document).ready(function() {
    // Initialize analytics charts
    initializeAnalyticsCharts();
    
    // Chart switching functionality
    $('[data-chart]').on('click', function() {
        const chartType = $(this).data('chart');
        const chartContainer = $(this).closest('.card').find('canvas');
        
        // Remove active class from all buttons in the same group
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        
        // Load new chart data
        loadChartData(chartContainer.attr('id'), chartType);
    });
});

// Initialize analytics charts
function initializeAnalyticsCharts() {
    // Member Growth Chart
    const memberGrowthCtx = document.getElementById('memberGrowthChart').getContext('2d');
    window.memberGrowthChart = new Chart(memberGrowthCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: '{{ _lang("New Members") }}',
                data: [],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            }
        }
    });
    
    // Transaction Trends Chart
    const transactionTrendsCtx = document.getElementById('transactionTrendsChart').getContext('2d');
    window.transactionTrendsChart = new Chart(transactionTrendsCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: '{{ _lang("Deposits") }}',
                data: [],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            }, {
                label: '{{ _lang("Withdrawals") }}',
                data: [],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }, {
                label: '{{ _lang("Loan Payments") }}',
                data: [],
                borderColor: '#ffc107',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: 2,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '{{ currency_symbol() }}' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                }
            }
        }
    });
    
    // Load initial chart data
    loadChartData('memberGrowthChart', 'member_growth');
    loadChartData('transactionTrendsChart', 'transaction_trends');
}

// Load chart data via AJAX
function loadChartData(canvasId, chartType) {
    $.ajax({
        url: '{{ route("dashboard.analytics_data") }}',
        method: 'GET',
        data: {
            type: chartType,
            range: '{{ request("range", "this_month") }}'
        },
        success: function(response) {
            updateChart(canvasId, chartType, response);
        },
        error: function(xhr, status, error) {
            console.error('Error loading chart data:', error);
        }
    });
}

// Update chart with new data
function updateChart(canvasId, chartType, data) {
    let chart;
    
    switch(canvasId) {
        case 'memberGrowthChart':
            chart = window.memberGrowthChart;
            if (chartType === 'member_growth') {
                chart.data.labels = data.months || [];
                chart.data.datasets[0].data = data.data || [];
                chart.data.datasets[0].label = '{{ _lang("New Members") }}';
                chart.data.datasets[0].borderColor = '#007bff';
                chart.data.datasets[0].backgroundColor = 'rgba(0, 123, 255, 0.1)';
            } else if (chartType === 'loan_performance') {
                chart.data.labels = data.labels || [];
                chart.data.datasets[0].data = data.data || [];
                chart.data.datasets[0].label = '{{ _lang("Loan Status") }}';
                chart.data.datasets[0].backgroundColor = data.colors || [];
                chart.type = 'doughnut';
            }
            break;
            
        case 'transactionTrendsChart':
            chart = window.transactionTrendsChart;
            if (chartType === 'transaction_trends') {
                chart.data.labels = data.dates || [];
                chart.data.datasets[0].data = data.deposits || [];
                chart.data.datasets[1].data = data.withdrawals || [];
                chart.data.datasets[2].data = data.loan_payments || [];
                chart.type = 'line';
            } else if (chartType === 'revenue_breakdown') {
                chart.data.labels = data.labels || [];
                chart.data.datasets[0].data = data.data || [];
                chart.data.datasets[0].backgroundColor = data.colors || [];
                chart.data.datasets[0].label = '{{ _lang("Revenue by Type") }}';
                chart.type = 'doughnut';
                // Hide other datasets for doughnut chart
                chart.data.datasets[1].hidden = true;
                chart.data.datasets[2].hidden = true;
            }
            break;
    }
    
    if (chart) {
        chart.update();
    }
}

// Refresh dashboard function
function refreshDashboard() {
    location.reload();
}

// Auto-refresh every 5 minutes
setInterval(function() {
    // Only refresh charts, not the entire page
    if (window.memberGrowthChart) {
        loadChartData('memberGrowthChart', 'member_growth');
    }
    if (window.transactionTrendsChart) {
        loadChartData('transactionTrendsChart', 'transaction_trends');
    }
}, 300000); // 5 minutes
</script>

<style>
/* Modern Dashboard Card Styling */
.dashboard-card-link {
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
}

.dashboard-card-link:hover {
    text-decoration: none;
    color: inherit;
    transform: translateY(-2px);
}

.modern-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.modern-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.modern-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #5f27cd, #00d2d3);
}

/* Primary Cards */
.members-card::before {
    background: linear-gradient(90deg, #5f27cd, #7c3aed);
}

.active-members-card::before {
    background: linear-gradient(90deg, #00d2d3, #54a0ff);
}

.loans-card::before {
    background: linear-gradient(90deg, #ff9ff3, #f368e0);
}

.revenue-card::before {
    background: linear-gradient(90deg, #00d2d3, #54a0ff);
}

/* Card Content Styling */
.modern-card .card-body {
    padding: 1.5rem;
    position: relative;
}

.modern-card .card-title {
    font-size: 14px;
    font-weight: 500;
    color: #6c757d;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-card .card-value {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 8px;
    line-height: 1.2;
}

.modern-card .card-subtitle {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.modern-card .card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    margin-left: 15px;
    flex-shrink: 0;
}

/* Icon Backgrounds */
.members-card .card-icon {
    background: linear-gradient(135deg, #5f27cd, #7c3aed);
}

.active-members-card .card-icon {
    background: linear-gradient(135deg, #00d2d3, #54a0ff);
}

.loans-card .card-icon {
    background: linear-gradient(135deg, #ff9ff3, #f368e0);
}

.revenue-card .card-icon {
    background: linear-gradient(135deg, #00d2d3, #54a0ff);
}

/* Secondary Cards */
.secondary-card {
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.secondary-card:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.secondary-card .card-body {
    padding: 1rem;
}

.secondary-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: white;
    margin: 0 auto 8px;
    background: linear-gradient(135deg, #5f27cd, #7c3aed);
}

.secondary-icon.warning {
    background: linear-gradient(135deg, #ff9ff3, #f368e0);
}

.secondary-icon.success {
    background: linear-gradient(135deg, #00d2d3, #54a0ff);
}

.secondary-icon.info {
    background: linear-gradient(135deg, #5f27cd, #7c3aed);
}

.secondary-icon.danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
}

.secondary-title {
    font-size: 12px;
    font-weight: 500;
    color: #6c757d;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.secondary-value {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modern-card .card-value {
        font-size: 24px;
    }
    
    .modern-card .card-icon {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .secondary-value {
        font-size: 16px;
    }
}

/* Legacy Support */
.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.border-left-primary {
    border-left: 4px solid #5f27cd !important;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.table-sm th, .table-sm td {
    padding: 0.5rem;
}

.badge {
    font-size: 0.75em;
}

/* Chart Sizing */
#memberGrowthChart, #transactionTrendsChart, #expenseOverview, #transactionAnalysis {
    max-height: 300px !important;
}

/* Animation for card loading */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modern-card {
    animation: fadeInUp 0.6s ease-out;
}

.modern-card:nth-child(1) { animation-delay: 0.1s; }
.modern-card:nth-child(2) { animation-delay: 0.2s; }
.modern-card:nth-child(3) { animation-delay: 0.3s; }
.modern-card:nth-child(4) { animation-delay: 0.4s; }
</style>
@endsection
