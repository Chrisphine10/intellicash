<div class="row px-2">
	<div class="col-lg-12">
		<table class="table table-bordered">
			<tr><td>{{ _lang('Name') }}</td><td>{{ $currency->full_name }} ({{ $currency->name }})</td></tr>
			<tr><td>{{ _lang('Exchange Rate') }}</td><td>{{ $currency->exchange_rate }}</td></tr>
			<tr><td>{{ _lang('Base Currency') }}</td><td>{{ $currency->base_currency }}</td></tr>
			<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(status($currency->status)) !!}</td></tr>
		</table>
	</div>
</div>

