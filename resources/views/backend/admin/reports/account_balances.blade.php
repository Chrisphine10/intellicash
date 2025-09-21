@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Account Balances') }}</span>
			</div>

			<div class="card-body">
				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.account_balances') }}" autocomplete="off">
						<div class="row">
              				@csrf

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Account Type') }}</label>
									<select class="form-control auto-select" name="account_type_id" data-selected="{{ isset($account_type_id) ? $account_type_id : old('account_type_id') }}" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\SavingsProduct::with('currency')->active()->get() as $savings_product)
											<option value="{{ $savings_product->id }}">{{ $savings_product->name }} ({{ $savings_product->currency->name }})</option>
										@endforeach
										<option value="all">{{ _lang('All Account Type') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Member') }}</label>
									<select class="form-control select2-ajax" data-table="members" data-value="id" data-display="first_name" data-display2="last_name" 
										name="member_id" data-where="3" data-placeholder="{{ _lang('All Member') }}">
										@if(isset($member_id) && $member_id != '')
											<option value="{{ $member_id }}">{{ \App\Models\Member::find($member_id)->name ?? _lang('All Member') }}</option>
										@endif
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_date_format(); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Account Balances') }}</h4>
				   <h5>{{ _lang('Account Type').': '.$account_type }}</h5>
				   <h5>{{ _lang('Date').': '. date($date_format) }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Member') }}</th>
						<th>{{ _lang('Account Number') }}</th>
						<th class="text-right">{{ _lang('Balance') }}</th>
						<th class="text-right">{{ _lang('Loan Guarantee') }}</th>
						<th class="text-right">{{ _lang('Current Balance') }}</th>
					</thead>
					<tbody>
						@if(isset($accounts))
						@foreach($accounts as $account)
							<tr>
								<td>{{ $account->member->name }}</td>
								<td>{{ $account->account_number }} - {{ $account->savings_type->name }} ({{ $account->savings_type->currency->name }})</td>
								<td class="text-right">{{ decimalPlace($account->balance, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->balance - $account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
							</tr>
						@endforeach
						@endif
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection