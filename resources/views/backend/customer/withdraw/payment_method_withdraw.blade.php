@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Withdraw via') }} {{ ucfirst($bankAccount->payment_method_type) }}</h4>
				<p class="text-center text-muted">{{ _lang('Withdraw money using') }} {{ $bankAccount->bank_name }} - {{ $bankAccount->account_name }}</p>
			</div>
			<div class="card-body">
				<form method="POST" action="{{ route('withdraw.manual_withdraw', 'payment_' . $bankAccount->id) }}" id="withdrawal-form">
					@csrf
					
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Select Account') }} <span class="text-danger">*</span></label>
								<select class="form-control" name="debit_account" id="debit_account" required>
									<option value="">{{ _lang('Select Account') }}</option>
									@foreach($accounts as $account)
										<option value="{{ $account->id }}" {{ old('debit_account') == $account->id ? 'selected' : '' }}>
											{{ $account->account_number }} - {{ $account->savings_type->name }} 
											({{ decimalPlace(get_account_balance($account->id, auth()->user()->member->id), currency($account->savings_type->currency->name)) }})
										</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Amount') }} <span class="text-danger">*</span></label>
								<input type="number" class="form-control" name="amount" id="amount" 
									   value="{{ old('amount') }}" step="0.01" min="1" required>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Description') }}</label>
								<textarea class="form-control" name="description" rows="3" 
										  placeholder="{{ _lang('Optional description for this withdrawal') }}">{{ old('description') }}</textarea>
							</div>
						</div>
					</div>

					<!-- Recipient Details -->
					<div class="row">
						<div class="col-md-12">
							<h5 class="text-primary">{{ _lang('Recipient Details') }}</h5>
						</div>
					</div>
					
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Recipient Name') }} <span class="text-danger">*</span></label>
								<input type="text" class="form-control" name="recipient_name" 
									   value="{{ old('recipient_name') }}" placeholder="Full name" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Mobile Number') }} <span class="text-danger">*</span></label>
								<input type="text" class="form-control" name="recipient_mobile" 
									   value="{{ old('recipient_mobile') }}" placeholder="07XXXXXXXX" required>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Account Number') }} <span class="text-danger">*</span></label>
								<input type="text" class="form-control" name="recipient_account" 
									   value="{{ old('recipient_account') }}" placeholder="Account number" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Bank Code') }}</label>
								<input type="text" class="form-control" name="recipient_bank_code" 
									   value="{{ old('recipient_bank_code') }}" placeholder="e.g., 001 for KCB">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<button type="submit" class="btn btn-primary btn-block">
									<i class="fa fa-money-bill-wave"></i> {{ _lang('Submit Withdrawal Request') }}
								</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>

		<!-- Payment Method Info -->
		<div class="card mt-4">
			<div class="card-header">
				<h5 class="header-title">{{ _lang('Payment Method Information') }}</h5>
			</div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-6">
						<p><strong>{{ _lang('Bank Account') }}:</strong> {{ $bankAccount->bank_name }} - {{ $bankAccount->account_name }}</p>
						<p><strong>{{ _lang('Account Number') }}:</strong> {{ $bankAccount->account_number }}</p>
					</div>
					<div class="col-md-6">
						<p><strong>{{ _lang('Payment Type') }}:</strong> {{ ucfirst($bankAccount->payment_method_type) }}</p>
						<p><strong>{{ _lang('Currency') }}:</strong> {{ $bankAccount->currency->name ?? 'KES' }}</p>
					</div>
				</div>
				<div class="alert alert-info">
					<i class="fa fa-info-circle"></i>
					{{ _lang('This withdrawal will be processed after tenant approval through the connected payment method.') }}
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Update recipient details based on payment method type
    var paymentType = '{{ $bankAccount->payment_method_type }}';
    
    if (paymentType === 'paystack') {
        $('input[name="recipient_mobile"]').attr('placeholder', '07XXXXXXXX (for MPesa)');
        $('input[name="recipient_account"]').attr('placeholder', 'Mobile number for MPesa');
    } else if (paymentType === 'buni') {
        $('input[name="recipient_mobile"]').attr('placeholder', 'Mobile number');
        $('input[name="recipient_account"]').attr('placeholder', 'Bank account number');
    }

    // Form validation
    $('#withdrawal-form').on('submit', function(e) {
        var amount = parseFloat($('#amount').val());
        var debitAccount = $('#debit_account').val();
        var recipientName = $('input[name="recipient_name"]').val();
        var recipientMobile = $('input[name="recipient_mobile"]').val();
        var recipientAccount = $('input[name="recipient_account"]').val();
        
        if (!debitAccount) {
            e.preventDefault();
            alert('{{ _lang("Please select an account") }}');
            return false;
        }
        
        if (!amount || amount <= 0) {
            e.preventDefault();
            alert('{{ _lang("Please enter a valid amount") }}');
            return false;
        }
        
        if (!recipientName || !recipientMobile || !recipientAccount) {
            e.preventDefault();
            alert('{{ _lang("Please fill in all recipient details") }}');
            return false;
        }
    });
});
</script>
@endsection
