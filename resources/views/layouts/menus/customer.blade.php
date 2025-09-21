<li>
	<a href="{{ route('dashboard.index') }}"><i class="fas fa-tachometer-alt"></i><span>{{ _lang('Dashboard') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-hand-holding-usd"></i><span>{{ _lang('Loans') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('loans.my_loans') }}">{{ _lang('My Loans') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('loans.loan_products') }}">{{ _lang('Apply New Loan') }}</a></li>
	</ul>
</li>

<li>
	<a href="{{ route('loans.calculator') }}"><i class="fas fa-calculator"></i><span>{{ _lang('Loan Calculator') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-exchange-alt"></i><span>{{ _lang('Transfer Money') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('transfer.own_account_transfer') }}">{{ _lang('Own Account Transfer') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('transfer.other_account_transfer') }}">{{ _lang('Others Account Transfer') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('funds_transfer.form') }}">{{ _lang('Funds Transfer') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('funds_transfer.history') }}">{{ _lang('Transfer History') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-credit-card"></i><span>{{ _lang('Payments') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('deposit.automatic_methods') }}">{{ _lang('Instant Deposit') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('deposit.manual_methods') }}">{{ _lang('Offline Deposit') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('trasnactions.pending_requests') }}?type=deposit_requests">{{ _lang('Pending Requests') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-money-check"></i><span>{{ _lang('Withdraw Money') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
        <li class="nav-item"><a class="nav-link" href="{{ route('withdraw.manual_methods') }}">{{ _lang('Withdraw Options') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('withdraw.buni.form') }}">{{ _lang('KCB Buni Withdraw') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('withdraw.history') }}">{{ _lang('Withdrawal History') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('withdraw.requests') }}">{{ _lang('Withdrawal Requests') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-chart-bar"></i><span>{{ _lang('Reports') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('customer_reports.account_statement') }}">{{ _lang('Account Statement') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('customer_reports.transactions_report') }}">{{ _lang('Transaction Report') }}</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('customer_reports.account_balances') }}">{{ _lang('Account Balance') }}</a></li>
    </ul>
</li>

<li>
	<a href="{{ route('audit.index') }}"><i class="fas fa-history"></i><span>{{ _lang('My Activity Log') }}</span></a>
</li>