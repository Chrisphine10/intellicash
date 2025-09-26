@php
$inbox = request_count('messages');
$deposit_requests = request_count('deposit_requests', true);
$withdraw_requests = request_count('withdraw_requests', true);
$member_requests = request_count('member_requests', true);
$pending_loans = request_count('pending_loans', true);
$upcomming_repayments = request_count('upcomming_repayments', true);
@endphp

<li>
	<a href="{{ route('dashboard.index') }}"><i class="fas fa-th-large"></i><span>{{ _lang('Dashboard') }}</span></a>
</li>

<li>
	<a href="{{ route('branches.index') }}"><i class="fas fa-building"></i><span>{{ _lang('Branches') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-user-friends"></i><span>{{ _lang('Members') }} {!! xss_clean($member_requests) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('members.index') }}">{{ _lang('Member List') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('members.create') }}">{{ _lang('Add Member') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('members.import') }}">{{ _lang('Bulk Import') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('custom_fields.index', ['members']) }}">{{ _lang('Custom Fields') }}</a></li>
		<li class="nav-item">
			<a class="nav-link" href="{{ route('members.pending_requests') }}">
			{{ _lang('Member Requests') }}
			{!! xss_clean($member_requests) !!}
			</a>
		</li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-hand-holding-usd"></i><span>{{ _lang('Loans') }} {!! xss_clean($pending_loans) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('loans.index') }}">{{ _lang('All Loans') }}</a></li>
		<li class="nav-item">
			<a class="nav-link" href="{{ route('loans.filter', 'pending') }}">
				{{ _lang('Pending Loans') }}
				{!! xss_clean($pending_loans) !!}
			</a>
		</li>
		<li class="nav-item"><a class="nav-link" href="{{ route('loans.filter', 'active') }}">{{ _lang('Active Loans') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('loans.admin_calculator') }}">{{ _lang('Loan Calculator') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('loan_products.index') }}">{{ _lang('Loan Products') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('custom_fields.index', ['loans']) }}">{{ _lang('Custom Fields') }}</a></li>
		
	</ul>
</li>

<li><a href="{{ route('loans.upcoming_loan_repayments') }}"><i class="fas fa-calendar-alt"></i><span>{{ _lang('Upcoming Payments') }} {!! xss_clean($upcomming_repayments) !!}</span></a></li>
<li><a href="{{ route('loan_payments.index') }}"><i class="fas fa-receipt"></i><span>{{ _lang('Loan Repayments') }}</span></a></li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-landmark"></i><span>{{ _lang('Accounts') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('savings_accounts.index') }}">{{ _lang('Member Accounts') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('interest_calculation.calculator') }}">{{ _lang('Interest Calculation') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('savings_products.index') }}">{{ _lang('Account Types') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-coins"></i><span>{{ _lang('Deposit') }} {!! xss_clean($deposit_requests) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.create') }}?type=deposit">{{ _lang('Deposit Money') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('deposit_requests.index') }}">
				{{ _lang('Deposit Requests') }}
				{!! xss_clean($deposit_requests) !!}
			</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-money-check"></i><span>{{ _lang('Withdraw') }} {!! xss_clean($withdraw_requests) !!}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.create') }}?type=withdraw">{{ _lang('Withdraw Money') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.withdrawal_requests.index') }}">
				{{ _lang('Withdrawal Requests') }}
				{!! xss_clean($withdraw_requests) !!}
			</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-wallet"></i><span>{{ _lang('Transactions') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.create') }}">{{ _lang('New Transaction') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('transactions.index') }}">{{ _lang('Transaction History') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('transaction_categories.index') }}">{{ _lang('Transaction Categories') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-money-bill-wave"></i><span>{{ _lang('Expense') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('expenses.index') }}">{{ _lang('Expenses') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('expense_categories.index') }}">{{ _lang('Categories') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-list-ul"></i><span>{{ _lang('Deposit Methods') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('automatic_methods.index') }}">{{ _lang('Online Gateways') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('deposit_methods.index') }}">{{ _lang('Offline Gateways') }}</a></li>
	</ul>
</li>

<li>
	<a href="{{ route('withdraw_methods.index') }}"><i class="fas fa-clipboard-list"></i><span>{{ _lang('Withdraw Methods') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-landmark"></i><span>{{ _lang('Bank Accounts') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('bank_accounts.index') }}">{{ _lang('Bank Accounts') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('bank_transactions.index') }}">{{ _lang('Bank Transactions') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-envelope"></i><span>{{ _lang('Messages') }}</span> {!! $inbox > 0 ? xss_clean('<div class="circle-animation"></div>') : '' !!}<span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('messages.compose') }}">{{ _lang('New Message') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('messages.inbox') }}">{{ _lang('Inbox Items') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('messages.sent') }}">{{ _lang('Sent Items') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-chart-bar"></i><span>{{ _lang('Reports') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<!-- Basic Reports -->
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.account_statement') }}">{{ _lang('Account Statement') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.account_balances') }}">{{ _lang('Account Balance') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.transactions_report') }}">{{ _lang('Transaction Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.expense_report') }}">{{ _lang('Expense Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.cash_in_hand') }}">{{ _lang('Cash In Hand') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.bank_transactions') }}">{{ _lang('Bank Transactions') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.bank_balances') }}">{{ _lang('Bank Account Balance') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.revenue_report') }}">{{ _lang('Revenue Report') }}</a></li>
		
		<!-- Loan Reports -->
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_report') }}">{{ _lang('Loan Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_due_report') }}">{{ _lang('Loan Due Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_repayment_report') }}">{{ _lang('Loan Repayment Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.borrowers_report') }}">{{ _lang('Borrowers Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_arrears_aging_report') }}">{{ _lang('Loan Arrears Aging Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.collections_report') }}">{{ _lang('Collections Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.disbursement_report') }}">{{ _lang('Disbursement Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.fees_report') }}">{{ _lang('Fees Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_officer_report') }}">{{ _lang('Loan Officer Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.loan_products_report') }}">{{ _lang('Loan Products Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.outstanding_report') }}">{{ _lang('Outstanding Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.portfolio_at_risk_report') }}">{{ _lang('Portfolio At Risk (PAR)') }}</a></li>
		
		<!-- Summary Reports -->
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.monthly_report') }}">{{ _lang('Monthly Report') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.at_glance_report') }}">{{ _lang('At a Glance Report') }}</a></li>
		
		<!-- Financial Statements -->
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.balance_sheet') }}">{{ _lang('Balance Sheet') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('reports.profit_loss_statement') }}">{{ _lang('Profit Loss Statement') }}</a></li>
	</ul>
</li>

<li>
	<a href="{{ route('audit.index') }}"><i class="fas fa-clipboard-list"></i><span>{{ _lang('Audit Trail') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-signature"></i><span>{{ _lang('E-Signature') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('esignature.esignature-documents.index') }}">{{ _lang('Documents') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('esignature.esignature-documents.create') }}">{{ _lang('Create Document') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-user-friends"></i><span>{{ _lang('System Users') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">{{ _lang('Manage Users') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('roles.index') }}">{{ _lang('Roles & Permission') }}</a></li>
	</ul>
</li>

@if(app('tenant')->isVslaEnabled())
<li>
	<a href="javascript: void(0);"><i class="fas fa-users"></i><span>{{ _lang('VSLA Management') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('vsla.settings.index') }}">{{ _lang('VSLA Settings') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('vsla.meetings.index') }}">{{ _lang('Meetings') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('vsla.transactions.index') }}">{{ _lang('VSLA Transactions') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('vsla.cycles.index') }}">{{ _lang('Cycle Management') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-vote-yea"></i><span>{{ _lang('Voting & Elections') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('voting.positions.index') }}">{{ _lang('Voting Positions') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('voting.elections.index') }}">{{ _lang('Elections') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('voting.elections.create') }}">{{ _lang('Create Election') }}</a></li>
	</ul>
</li>
@endif

        @if(app('tenant')->isAssetManagementEnabled())
        <li>
            <a href="javascript: void(0);"><i class="fas fa-building"></i><span>{{ _lang('Asset Management') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
            <ul class="nav-second-level" aria-expanded="false">
                <li class="nav-item"><a class="nav-link" href="{{ route('asset-management.dashboard') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('assets.index') }}">{{ _lang('Assets') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('asset-categories.index') }}">{{ _lang('Categories') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('asset-leases.index') }}">{{ _lang('Leases') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('asset-maintenance.index') }}">{{ _lang('Maintenance') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('asset-reports.index') }}">{{ _lang('Reports') }}</a></li>
            </ul>
        </li>
        @endif

        @if(app('tenant')->isPayrollEnabled())
        <li>
            <a href="javascript: void(0);"><i class="fas fa-money-bill-wave"></i><span>{{ _lang('Payroll Management') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
            <ul class="nav-second-level" aria-expanded="false">
                <li class="nav-item"><a class="nav-link" href="{{ route('payroll.periods.index') }}">{{ _lang('Payroll Periods') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('payroll.employees.index') }}">{{ _lang('Employees') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('payroll.deductions.index') }}">{{ _lang('Deductions') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('payroll.benefits.index') }}">{{ _lang('Benefits') }}</a></li>
            </ul>
        </li>
        @endif

<li>
	<a href="javascript: void(0);"><i class="ti-settings"></i><span>{{ _lang('System Settings') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('modules.index') }}">{{ _lang('Module Management') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('settings.index') }}">{{ _lang('System Settings') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('currency.index') }}">{{ _lang('Currency Management') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('email_templates.index') }}">{{ _lang('Notification Templates') }}</a></li>
	</ul>
</li>