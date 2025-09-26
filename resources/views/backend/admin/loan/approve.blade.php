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

						<!-- Loan Information Display -->
						<div class="col-lg-12">
							<h5 class="text-primary"><i class="fas fa-info-circle"></i> {{ _lang('Loan Information') }}</h5>
							<div class="row">
								<div class="col-lg-4">
									<div class="form-group">
										<label class="control-label">{{ _lang('Loan ID') }}</label>
										<input type="text" class="form-control" value="{{ $loan->loan_id }}" readonly style="background-color: #f8f9fa;">
										<input type="hidden" name="loan_id" value="{{ $loan->loan_id }}">
										<small class="form-text text-muted">{{ _lang('This ID is automatically generated and cannot be changed') }}</small>
									</div>
								</div>
								
								<div class="col-lg-4">
									<div class="form-group">
										<label class="control-label">{{ _lang('Release Date') }}</label>
										<input type="text" class="form-control" value="{{ $loan->release_date ? $loan->release_date : _lang('Not set') }}" readonly style="background-color: #f8f9fa;">
										<input type="hidden" name="release_date" value="{{ $loan->getRawOriginal('release_date') }}">
										<small class="form-text text-muted">{{ _lang('Release date was set during loan application') }}</small>
									</div>
								</div>
								
								<div class="col-lg-4">
									<div class="form-group">
										<label class="control-label">{{ _lang('First Payment Date') }}</label>
										<input type="text" class="form-control" value="{{ $loan->first_payment_date }}" readonly style="background-color: #f8f9fa;">
										<input type="hidden" name="first_payment_date" value="{{ $loan->getRawOriginal('first_payment_date') }}">
										<small class="form-text text-muted">{{ _lang('Calculated based on loan terms') }}</small>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Bank Account for Disbursement') }} <span class="text-danger">*</span></label>
								<select class="form-control auto-select" data-selected="{{ old('bank_account_id') }}" name="bank_account_id" id="bank_account_id" required>
									<option value="">{{ _lang('Select Bank Account') }}</option>
									@foreach($bankAccounts as $bankAccount)
									<option value="{{ $bankAccount->id }}" data-balance="{{ $bankAccount->current_balance }}">
										{{ $bankAccount->bank_name }} - {{ $bankAccount->account_name }} 
										({{ $bankAccount->account_number }}) 
										- {{ decimalPlace($bankAccount->current_balance, currency($bankAccount->currency->name)) }}
									</option>
									@endforeach
								</select>
								<small class="form-text text-muted">
									<i class="fas fa-info-circle"></i> {{ _lang('Select the bank account from which the loan will be disbursed') }}
								</small>
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
    // Add form validation (only for bank account selection)
    $('#approve-btn').click(function(e) {
        var bankAccountId = $('select[name="bank_account_id"]').val();
        
        if (!bankAccountId) {
            e.preventDefault();
            alert('Please select a bank account for loan disbursement');
            return false;
        }
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Processing...');
    });
});
</script>
@endsection
