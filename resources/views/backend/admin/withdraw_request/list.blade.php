@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Withdraw Requests') }}</span>
			</div>
			<div class="card-body">
				<div class="row mb-3">
					<div class="col-md-2">
						<label>{{ _lang('Status') }}</label>
						<select name="status" class="form-control select-filter">
							<option value="0">{{ _lang('Pending') }}</option>
							<option value="2">{{ _lang('Approved') }}</option>
							<option value="1">{{ _lang('Rejected') }}</option>
							<option value="">{{ _lang('All') }}</option>
						</select>
					</div>
					<div class="col-md-2">
						<label>{{ _lang('From Date') }}</label>
						<input type="date" name="from_date" class="form-control select-filter">
					</div>
					<div class="col-md-2">
						<label>{{ _lang('To Date') }}</label>
						<input type="date" name="to_date" class="form-control select-filter">
					</div>
					<div class="col-md-2">
						<label>{{ _lang('Min Amount') }}</label>
						<input type="number" name="min_amount" class="form-control select-filter" step="0.01" placeholder="0.00">
					</div>
					<div class="col-md-2">
						<label>{{ _lang('Max Amount') }}</label>
						<input type="number" name="max_amount" class="form-control select-filter" step="0.01" placeholder="0.00">
					</div>
					<div class="col-md-2">
						<label>{{ _lang('Member') }}</label>
						<input type="text" name="member_search" class="form-control select-filter" placeholder="{{ _lang('Search by name') }}">
					</div>
				</div>
				<div class="row mb-3">
					<div class="col-md-12">
						<button type="button" class="btn btn-secondary btn-sm" id="clear-filters">
							<i class="fas fa-times"></i> {{ _lang('Clear Filters') }}
						</button>
					</div>
				</div>
			</div>
			<div class="card-body">
				<table id="withdraw_requests_table" class="table table-bordered">
					<thead>
					    <tr>
							<th>{{ _lang('Member') }}</th>
						    <th>{{ _lang('Account Number') }}</th>
							<th>{{ _lang('Currency') }}</th>
							<th>{{ _lang('Amount') }}</th>
							<th>{{ _lang('Method') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection

@section('js-script')
<script src="{{ asset('public/backend/assets/js/datatables/withdraw_requests.js?v=1.0') }}"></script>
@endsection