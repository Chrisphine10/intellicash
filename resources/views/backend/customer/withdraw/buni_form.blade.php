@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h4 class="header-title text-center">{{ _lang('Withdraw via KCB Buni') }}</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> {{ _lang('How it works:') }}</h5>
                    <ul class="mb-0">
                        <li>{{ _lang('Enter your mobile phone number where you want to receive the money') }}</li>
                        <li>{{ _lang('The money will be sent directly to your mobile phone via KCB Buni') }}</li>
                        <li>{{ _lang('You will receive an SMS confirmation once the transfer is complete') }}</li>
                        <li>{{ _lang('Minimum withdrawal amount: 10 KES') }}</li>
                    </ul>
                </div>

                <form method="post" class="validate" autocomplete="off" action="{{ route('withdraw.buni.process') }}" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Select Account') }}</label>
                        <div class="col-xl-9">
                            <select class="form-control auto-select" name="debit_account" required>
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
                        <label class="col-xl-3 col-form-label">{{ _lang('Mobile Number') }}</label>
                        <div class="col-xl-9">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">+254</span>
                                </div>
                                <input type="text" class="form-control" name="mobile_number" value="{{ old('mobile_number', auth()->user()->member->mobile) }}" placeholder="712345678" required>
                            </div>
                            <small class="form-text text-muted">{{ _lang('Enter your mobile number without the country code (9-10 digits, e.g., 712345678)') }}</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-xl-3 col-form-label">{{ _lang('Amount') }}</label>
                        <div class="col-xl-9">
                            <div class="input-group">
                                <input type="number" class="form-control" name="amount" value="{{ old('amount') }}" step="0.01" min="10" required>
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
                            <textarea class="form-control" name="description" rows="3" placeholder="{{ _lang('Optional description for this withdrawal') }}">{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-xl-9 offset-xl-3">
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-triangle"></i> {{ _lang('Important:') }}</h6>
                                <ul class="mb-0">
                                    <li>{{ _lang('Make sure your mobile number is correct') }}</li>
                                    <li>{{ _lang('The money will be sent to this number immediately') }}</li>
                                    <li>{{ _lang('You will receive an SMS confirmation') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <div class="col-xl-9 offset-xl-3">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-mobile-alt mr-2"></i>{{ _lang('Withdraw to Mobile') }}
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
    // Format mobile number input
    $('input[name="mobile_number"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 10) {
            value = value.substring(0, 10);
        }
        $(this).val(value);
        
        // Validate mobile number length
        if (value.length < 9) {
            $(this).addClass('is-invalid');
            $('#mobile_error').remove();
            $(this).parent().after('<div id="mobile_error" class="invalid-feedback d-block">{{ _lang("Mobile number must be 9-10 digits") }}</div>');
        } else {
            $(this).removeClass('is-invalid');
            $('#mobile_error').remove();
        }
    });

    // Update amount validation based on selected account
    $('select[name="debit_account"]').on('change', function() {
        let selectedOption = $(this).find('option:selected');
        let balance = parseFloat(selectedOption.data('balance')) || 0;
        
        $('input[name="amount"]').attr('max', balance);
        
        if (balance < 10) {
            $('input[name="amount"]').prop('disabled', true);
            $('button[type="submit"]').prop('disabled', true);
            alert('{{ _lang("Insufficient balance for withdrawal") }}');
        } else {
            $('input[name="amount"]').prop('disabled', false);
            $('button[type="submit"]').prop('disabled', false);
        }
    });

    // Validate amount on input
    $('input[name="amount"]').on('input', function() {
        let amount = parseFloat($(this).val()) || 0;
        let selectedAccount = $('select[name="debit_account"] option:selected');
        let balance = parseFloat(selectedAccount.data('balance')) || 0;
        
        if (amount > balance) {
            $(this).addClass('is-invalid');
            $('button[type="submit"]').prop('disabled', true);
        } else if (amount < 10) {
            $(this).addClass('is-invalid');
            $('button[type="submit"]').prop('disabled', true);
        } else {
            $(this).removeClass('is-invalid');
            $('button[type="submit"]').prop('disabled', false);
        }
    });
});
</script>
@endsection
