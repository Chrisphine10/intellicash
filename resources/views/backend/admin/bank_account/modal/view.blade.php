<table class="table table-bordered">
	<tr><td>{{ _lang('Opening Date') }}</td><td>{{ $bankAccount->opening_date }}</td></tr>
	<tr><td>{{ _lang('Bank Name') }}</td><td>{{ $bankAccount->bank_name }}</td></tr>
	<tr><td>{{ _lang('Currency') }}</td><td>{{ $bankAccount->currency->name }}</td></tr>
	<tr><td>{{ _lang('Account Name') }}</td><td>{{ $bankAccount->account_name }}</td></tr>
	<tr><td>{{ _lang('Account Number') }}</td><td>{{ $bankAccount->account_number }}</td></tr>
	<tr><td>{{ _lang('Description') }}</td><td>{{ $bankAccount->description }}</td></tr>
</table>

