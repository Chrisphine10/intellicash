@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h4 class="header-title text-center">{{ _lang('Funds Transfer') }}</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> {{ _lang('Transfer Options:') }}</h5>
                    <ul class="mb-0">
                        <li><strong>KCB Buni:</strong> {{ _lang('Transfer to any KCB account using account number') }}</li>
                        <li><strong>Paystack MPesa:</strong> {{ _lang('Transfer to member MPesa account using mobile number') }}</li>
                        <li>{{ _lang('Minimum transfer amount: 10 KES') }}</li>
                        <li>{{ _lang('Transfers are processed immediately') }}</li>
                    </ul>
                </div>

                <form method="post" class="validate" autocomplete="off" action="{{ route('funds_transfer.process') }}" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Select Account') }}</label>
                        <div class="col-xl-9">
                            <select class="form-control auto-select" name="debit_account" id="debit_account" required>
                                <option value="">{{ _lang('Select Account') }}</option>
                                @foreach($savingsAccounts as $account)
                                    <option value="{{ $account->id }}" data-balance="{{ get_account_balance($account->id, auth()->user()->member->id) }}">
                                        {{ $account->savings_type->name }} - {{ decimalPlace(get_account_balance($account->id, auth()->user()->member->id), currency($account->savings_type->currency->name)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Transfer Type') }}</label>
                        <div class="col-xl-9">
                            <select class="form-control auto-select" name="transfer_type" id="transfer_type" required>
                                <option value="">{{ _lang('Select Transfer Type') }}</option>
                                <option value="kcb_buni">{{ _lang('KCB Buni (Bank Account)') }}</option>
                                <option value="paystack_mpesa">{{ _lang('Paystack MPesa (Mobile Money)') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- KCB Buni Fields -->
                    <div id="kcb_buni_fields" style="display: none;">
                        <div class="form-group row">
                            <label class="col-xl-3 col-form-label">{{ _lang('Recipient Account Number') }}</label>
                            <div class="col-xl-9">
                                <input type="text" class="form-control" name="recipient_account" placeholder="Enter KCB account number">
                                <small class="form-text text-muted">{{ _lang('Enter the KCB account number to receive the funds') }}</small>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-xl-3 col-form-label">{{ _lang('Bank Code') }}</label>
                            <div class="col-xl-9">
                                <input type="text" class="form-control" name="beneficiary_bank_code" value="01" placeholder="Bank code (default: 01 for KCB)">
                                <small class="form-text text-muted">{{ _lang('Bank code for the recipient account (01 for KCB)') }}</small>
                            </div>
                        </div>
                    </div>

                    <!-- Paystack MPesa Fields -->
                    <div id="paystack_mpesa_fields" style="display: none;">
                        <div class="form-group row">
                            <label class="col-xl-3 col-form-label">{{ _lang('Recipient Mobile Number') }}</label>
                            <div class="col-xl-9">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">+254</span>
                                    </div>
                                    <input type="text" class="form-control" name="recipient_mobile" placeholder="7XXXXXXXX">
                                </div>
                                <small class="form-text text-muted">{{ _lang('Enter mobile number without country code (e.g., 712345678)') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Beneficiary Name') }}</label>
                        <div class="col-xl-9">
                            <input type="text" class="form-control" name="beneficiary_name" value="{{ old('beneficiary_name') }}" placeholder="Enter recipient name" required>
                            <small class="form-text text-muted">{{ _lang('Full name of the person receiving the funds') }}</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Amount') }}</label>
                        <div class="col-xl-9">
                            <div class="input-group">
                                <input type="number" class="form-control" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="10" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">KES</span>
                                </div>
                            </div>
                            <small class="form-text text-muted">{{ _lang('Minimum amount: 10 KES') }}</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Description') }}</label>
                        <div class="col-xl-9">
                            <textarea class="form-control" name="description" rows="3" placeholder="{{ _lang('Optional description for this transfer') }}">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-xl-9 offset-xl-3">
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle"></i> {{ _lang('Important:') }}</h6>
                                <ul class="mb-0">
                                    <li>{{ _lang('Make sure all recipient details are correct') }}</li>
                                    <li>{{ _lang('Transfers cannot be reversed once processed') }}</li>
                                    <li>{{ _lang('You will receive a confirmation notification') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-xl-9 offset-xl-3">
                            <button type="submit" class="btn btn-primary btn-block" id="submit_btn">
                                <i class="fas fa-paper-plane mr-2"></i>{{ _lang('Transfer Funds') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Show/hide fields based on transfer type
    $('#transfer_type').on('change', function() {
        const transferType = $(this).val();
        
        // Hide all conditional fields
        $('#kcb_buni_fields, #paystack_mpesa_fields').hide();
        $('#kcb_buni_fields input, #paystack_mpesa_fields input').prop('required', false);
        
        if (transferType === 'kcb_buni') {
            $('#kcb_buni_fields').show();
            $('#kcb_buni_fields input').prop('required', true);
        } else if (transferType === 'paystack_mpesa') {
            $('#paystack_mpesa_fields').show();
            $('#paystack_mpesa_fields input').prop('required', true);
        }
        
        validateForm();
    });

    // Format mobile number input
    $('input[name="recipient_mobile"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        $(this).val(value);
        
        // Validate mobile number length
        if (value.length < 9 && value.length > 0) {
            $(this).addClass('is-invalid');
            $('#mobile_transfer_error').remove();
            $(this).parent().after('<div id="mobile_transfer_error" class="invalid-feedback d-block">{{ _lang("Mobile number must be 9-10 digits") }}</div>');
        } else {
            $(this).removeClass('is-invalid');
            $('#mobile_transfer_error').remove();
        }
    });

    // Update amount validation based on selected account
    $('#debit_account').on('change', function() {
        validateForm();
    });

    // Validate amount on input
    $('#amount').on('input', function() {
        validateForm();
    });

    function validateForm() {
        const selectedAccount = $('#debit_account option:selected');
        const balance = parseFloat(selectedAccount.data('balance')) || 0;
        const amount = parseFloat($('#amount').val()) || 0;
        const transferType = $('#transfer_type').val();
        
        let isValid = true;
        
        // Check account balance
        if (amount > balance) {
            $('#amount').addClass('is-invalid');
            isValid = false;
        } else if (amount < 10) {
            $('#amount').addClass('is-invalid');
            isValid = false;
        } else {
            $('#amount').removeClass('is-invalid');
        }
        
        // Check if transfer type is selected
        if (!transferType) {
            isValid = false;
        }
        
        // Check if account is selected
        if (!selectedAccount.val()) {
            isValid = false;
        }
        
        // Check required fields based on transfer type
        if (transferType === 'kcb_buni') {
            const accountNumber = $('input[name="recipient_account"]').val();
            if (!accountNumber) {
                isValid = false;
            }
        } else if (transferType === 'paystack_mpesa') {
            const mobileNumber = $('input[name="recipient_mobile"]').val();
            if (!mobileNumber || mobileNumber.length < 9 || mobileNumber.length > 10) {
                isValid = false;
            }
        }
        
        // Enable/disable submit button
        $('#submit_btn').prop('disabled', !isValid);
        
        if (balance < 10) {
            $('#submit_btn').prop('disabled', true);
            if (selectedAccount.val()) {
                alert('{{ _lang("Insufficient balance for transfer") }}');
            }
        }
    }

    // Initial validation
    validateForm();
});
</script>
@endsection
