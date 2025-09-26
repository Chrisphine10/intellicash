@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="alert alert-info">
			<span>{{ _lang('Amount greater than zero will post to user account') }}</span>
		</div>
		
		@if(count($users) == 0)
		<div class="alert alert-warning">
			<span>{{ _lang('No interest calculated for the selected date range and account type') }}</span>
		</div>
		@endif 

		<form method="post" action="{{ route('interest_calculation.interest_posting') }}">
			@csrf
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span class="panel-title">{{ _lang('Interest Review') }}</span>
					@if(count($users) > 0)
					<button class="btn btn-primary btn-xs float-right" type="submit">{{ _lang('POST INTEREST') }}</button>
					@endif
				</div>

				<input type="hidden" name="account_type_id" value="{{ $account_type_id }}"/>
				<input type="hidden" name="start_date" value="{{ $start_date }}"/>
				<input type="hidden" name="end_date" value="{{ $end_date }}"/>
				<input type="hidden" name="posting_date" value="{{ $posting_date }}"/>

				@php  $date_format = get_date_format(); @endphp

				<div class="card-body">
					@if(count($users) > 0)
					<div class="row mb-3">
						<div class="col-md-12">
							<div class="alert alert-success">
								<h5>{{ _lang('Calculation Summary') }}</h5>
								<p><strong>{{ _lang('Account Type') }}:</strong> {{ App\Models\SavingsProduct::find($account_type_id)->name ?? 'N/A' }}</p>
								<p><strong>{{ _lang('Interest Rate') }}:</strong> {{ App\Models\SavingsProduct::find($account_type_id)->interest_rate ?? 'N/A' }}%</p>
								<p><strong>{{ _lang('Date Range') }}:</strong> {{ date($date_format, strtotime($start_date)) }} - {{ date($date_format, strtotime($end_date)) }}</p>
								<p><strong>{{ _lang('Total Accounts') }}:</strong> {{ count($users) }}</p>
								<p><strong>{{ _lang('Total Interest') }}:</strong> {{ decimalPlace(array_sum(array_column($users, 'interest')), currency()) }}</p>
							</div>
						</div>
					</div>
					@endif
					
					<table class="table table-bordered data-table">
						<thead>
						<tr>
							<th>{{ _lang('User') }}</th>
							<th>{{ _lang('Account') }}</th>
							<th>{{ _lang('Type') }}</th>
							<th>{{ _lang('Interest') }}</th>
							<th>{{ _lang('Date Range') }}</th>
						</tr>
						</thead>
						<tbody>
							@foreach($users as $user)
							<tr>
								<td>{{ $user['member']->name }}</td>
								<td>{{ $user['account']->account_number }}</td>
								<td>{{ $user['account']->savings_type->name }}</td>
								<td>{{ decimalPlace($user['interest'], currency($user['account']->savings_type->currency->name)) }}</td>
								<td>
									{{ date($date_format, strtotime($start_date)) . ' - ' . date($date_format, strtotime($end_date))  }}
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
			@foreach($users as $user)
				@if($user['interest'] > 0)
				<input type="hidden" name="member_id[]" value="{{ $user['member_id'] }}"/>
				<input type="hidden" name="interest[]" value="{{ $user['interest'] }}"/>
				<input type="hidden" name="account_id[]" value="{{ $user['account']->id }}"/>
				@endif
			@endforeach
		</form>
	</div>
</div>
@endsection

