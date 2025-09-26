@extends('layouts.app')

@section('content')
@php $permissions = permission_list(); @endphp
<div class="row">
	@if (in_array('dashboard.total_customer_widget', $permissions))
	<div class="col-xl-3 col-md-6">
		<a href="{{ route('members.index') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card members-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Total Members') }}</div>
							<div class="card-value">{{ $total_customer }}</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-users"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>
	@endif

	@if (in_array('dashboard.deposit_requests_widget',$permissions))
	<div class="col-xl-3 col-md-6">
		<a href="{{ route('deposit_requests.index') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card deposit-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Deposit Requests') }}</div>
							<div class="card-value">{{ request_count('deposit_requests') }}</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-calendar-alt"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>
	@endif

	@if (in_array('dashboard.withdraw_requests_widget',$permissions))
	<div class="col-xl-3 col-md-6">
		<a href="{{ route('withdraw_requests.index') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card withdraw-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Withdraw Requests') }}</div>
							<div class="card-value">{{ request_count('withdraw_requests') }}</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-coins"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>
	@endif

	@if (in_array('dashboard.loan_requests_widget',$permissions))
	<div class="col-xl-3 col-md-6">
		<a href="{{ route('loans.filter', 'pending') }}" class="dashboard-card-link">
			<div class="card mb-4 dashboard-card modern-card pending-loans-card">
				<div class="card-body">
					<div class="d-flex align-items-center">
						<div class="flex-grow-1">
							<div class="card-title">{{ _lang('Pending Loans') }}</div>
							<div class="card-value">{{ request_count('pending_loans') }}</div>
						</div>
						<div class="card-icon">
							<i class="fas fa-dollar-sign"></i>
						</div>
					</div>
				</div>
			</div>
		</a>
	</div>
	@endif
</div>

<div class="row">
	@if (in_array('dashboard.expense_overview_widget',$permissions))
	<div class="col-lg-4 col-md-5 mb-4">
		<div class="card h-100">
			<div class="card-header d-flex align-items-center">
				<span>{{ _lang('Expense Overview').' - '.date('M Y') }}</span>
			</div>
			<div class="card-body">
				<canvas id="expenseOverview"></canvas>
			</div>
		</div>
	</div>
	@endif

	@if (in_array('dashboard.deposit_withdraw_analytics',$permissions))
	<div class="col-lg-8 col-md-7 mb-4">
		<div class="card h-100">
			<div class="card-header d-flex align-items-center">
				<span>{{ _lang('Deposit & Withdraw Analytics').' - '.date('Y')  }}</span>
				<select class="filter-select ml-auto py-0 auto-select" data-selected="{{ base_currency_id() }}">
					@foreach(\App\Models\Currency::where('status',1)->get() as $currency)
					<option value="{{ $currency->id }}" data-symbol="{{ currency_symbol($currency->name) }}">{{ $currency->name }}</option>
					@endforeach
				</select>
			</div>
			<div class="card-body">
				<canvas id="transactionAnalysis"></canvas>
			</div>
		</div>
	</div>
	@endif
</div>

@if (in_array('dashboard.active_loan_balances',$permissions))
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
@endif

@if (in_array('dashboard.due_loan_list',$permissions))
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
@endif

@if (in_array('dashboard.recent_transaction_widget',$permissions))
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
@endif
@endsection

@section('js-script')
<script src="{{ asset('public/backend/plugins/chartJs/chart.min.js') }}"></script>
<script src="{{ asset('public/backend/assets/js/dashboard.js') }}"></script>
@endsection

<style>
/* Modern Dashboard Card Styling for User Dashboard */
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

/* User Dashboard Card Variants */
.members-card::before {
    background: linear-gradient(90deg, #5f27cd, #7c3aed);
}

.deposit-card::before {
    background: linear-gradient(90deg, #00d2d3, #54a0ff);
}

.withdraw-card::before {
    background: linear-gradient(90deg, #ff9ff3, #f368e0);
}

.pending-loans-card::before {
    background: linear-gradient(90deg, #ff6b6b, #ee5a52);
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

/* Icon Backgrounds for User Dashboard */
.members-card .card-icon {
    background: linear-gradient(135deg, #5f27cd, #7c3aed);
}

.deposit-card .card-icon {
    background: linear-gradient(135deg, #00d2d3, #54a0ff);
}

.withdraw-card .card-icon {
    background: linear-gradient(135deg, #ff9ff3, #f368e0);
}

.pending-loans-card .card-icon {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
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
