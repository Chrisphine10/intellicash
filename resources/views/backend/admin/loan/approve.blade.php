@extends('layouts.app')

@section('content')
<style>
.alert-info {
    background-color: #d1ecf1;
    border-color: #bee5eb;
    color: #0c5460;
}
.text-primary {
    color: #007bff !important;
}
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
.text-danger {
    color: #dc3545 !important;
}
</style>
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header text-center">
				<span class="panel-title">{{ _lang('Confirm Loan Approval') }}</span>
			</div>
			<div class="card-body">
				@if($errors->any())
					<div class="alert alert-danger">
						<ul class="mb-0">
							@foreach($errors->all() as $error)
								<li>{{ $error }}</li>
							@endforeach
						</ul>
					</div>
				@endif
				
				<form method="post" class="validate" autocomplete="off" action="{{ route('loans.approve', $loan->id) }}">
					@csrf
					<div class="row">
						<div class="col-lg-12">
							<div class="alert alert-info">
								<i class="fas fa-info-circle"></i>
								<strong>{{ _lang('Required Information') }}</strong><br>
								{{ _lang('Please ensure all required fields are filled before approving the loan.') }}
							</div>
						</div>
						
						<div class="col-lg-12">
							<table class="table table-bordered">
								<tr>
									<td>{{ _lang("Loan Type") }}</td>
									<td>{{ $loan->loan_product->name }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Borrower") }}</td>
									<td>{{ $loan->borrower->first_name.' '.$loan->borrower->last_name }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Member No") }}</td>
									<td>{{ $loan->borrower->member_no }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Status") }}</td>
									<td>
									@if($loan->status == 0)
									{!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
									@elseif($loan->status == 1)
									{!! xss_clean(show_status(_lang('Approved'), 'success')) !!}
									@elseif($loan->status == 2)
									{!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
									@elseif($loan->status == 3)
									{!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
									@endif
									</td>
								</tr>
								<tr>
									<td>{{ _lang("Applied Amount") }}</td>
									<td>
									{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}
									</td>
								</tr>
								<tr>
									<td>{{ _lang("Late Payment Penalties") }}</td>
									<td>{{ $loan->late_payment_penalties }} %</td>
								</tr>
							</table>
						</div>

						<!-- Required Fields Section -->
						<div class="col-lg-12">
							<h5 class="text-primary"><i class="fas fa-edit"></i> {{ _lang('Required Information for Approval') }}</h5>
							<div class="row">
								<div class="col-lg-4">
									<div class="form-group">
										<label class="control-label">{{ _lang('Loan ID') }} <span class="text-danger">*</span></label>
										<input type="text" class="form-control" name="loan_id" value="{{ old('loan_id', $loan->loan_id) }}" required>
										@if($errors->has('loan_id'))
											<span class="text-danger">{{ $errors->first('loan_id') }}</span>
										@endif
									</div>
								</div>
								
								<div class="col-lg-4">
									<div class="form-group">
										<label class="control-label">{{ _lang('Release Date') }} <span class="text-danger">*</span></label>
										<input type="date" class="form-control" name="release_date" value="{{ old('release_date', $loan->release_date) }}" required>
										@if($errors->has('release_date'))
											<span class="text-danger">{{ $errors->first('release_date') }}</span>
										@endif
									</div>
								</div>
								
								<div class="col-lg-4">
									<div class="form-group">
										<label class="control-label">{{ _lang('First Payment Date') }} <span class="text-danger">*</span></label>
										<input type="date" class="form-control" name="first_payment_date" value="{{ old('first_payment_date', $loan->first_payment_date) }}" required>
										@if($errors->has('first_payment_date'))
											<span class="text-danger">{{ $errors->first('first_payment_date') }}</span>
										@endif
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Credit Account') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('account_id', 'cash') }}" name="account_id" id="account_id" required>
									<option value="cash">{{ _lang('Cash Handover') }}</option>
									@foreach($accounts as $account)
									<option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name.' - '.$account->savings_type->currency->name }})</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary" id="approve-btn"><i class="fas fa-check-circle mr-1"></i>{{ _lang('Confirm') }}</button>
								<a href="{{ url()->previous() }}" class="btn btn-danger"><i class="fas fa-undo mr-1"></i>{{ _lang('Back') }}</a>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@section('script')
<script>
$(document).ready(function() {
    // Add form validation
    $('#approve-btn').click(function(e) {
        var loanId = $('input[name="loan_id"]').val();
        var releaseDate = $('input[name="release_date"]').val();
        var firstPaymentDate = $('input[name="first_payment_date"]').val();
        var accountId = $('select[name="account_id"]').val();
        
        if (!loanId || !releaseDate || !firstPaymentDate || !accountId) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return false;
        }
        
        // Check if first payment date is after release date
        if (new Date(firstPaymentDate) <= new Date(releaseDate)) {
            e.preventDefault();
            alert('First Payment Date must be after Release Date');
            return false;
        }
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Processing...');
    });
});
</script>
@endsection
