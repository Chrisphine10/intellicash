@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <span class="panel-title">{{ _lang('New Lease Request') }}</span>
                    <a class="btn btn-secondary btn-sm float-right" href="{{ route('lease-requests.member.index') }}">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                    </a>
                </div>
                <div class="card-body">
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('lease-requests.member.store') }}" id="lease-request-form">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Select Asset') }} <span class="required">*</span></label>
                                    <select class="form-control auto-select" name="asset_id" id="asset_id" required>
                                        <option value="">{{ _lang('Select Asset') }}</option>
                                        @foreach($assets as $asset)
                                            <option value="{{ $asset->id }}" data-daily-rate="{{ $asset->lease_rate }}" data-description="{{ $asset->description }}">
                                                {{ $asset->name }} ({{ $asset->category->name ?? 'N/A' }}) - {{ formatAmount($asset->lease_rate) }}/day
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Start Date') }} <span class="required">*</span></label>
                                    <input type="date" class="form-control" name="start_date" id="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Duration (Days)') }} <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="requested_days" id="requested_days" value="{{ old('requested_days') }}" min="1" max="365" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('End Date') }}</label>
                                    <input type="date" class="form-control" name="end_date" id="end_date" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Daily Rate') }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="daily_rate" readonly>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ get_currency_symbol() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Total Amount') }}</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="total_amount" readonly>
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ get_currency_symbol() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Deposit Amount') }}</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="deposit_amount" id="deposit_amount" value="{{ old('deposit_amount', 0) }}" min="0" step="0.01">
                                        <div class="input-group-append">
                                            <span class="input-group-text">{{ get_currency_symbol() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Payment Account') }} <span class="required">*</span></label>
                                    <select class="form-control auto-select" name="payment_account_id" required>
                                        <option value="">{{ _lang('Select Account') }}</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->account_number }} ({{ $account->savings_type->name ?? 'N/A' }}) - {{ formatAmount(get_account_balance($account->id, $member->id)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Reason for Lease') }} <span class="required">*</span></label>
                                    <textarea class="form-control" name="reason" rows="4" required>{{ old('reason') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="terms_accepted" id="terms_accepted" required>
                                        <label class="form-check-label" for="terms_accepted">
                                            {{ _lang('I agree to the terms and conditions for asset leasing') }} <span class="required">*</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> {{ _lang('Submit Request') }}
                                    </button>
                                    <a href="{{ route('lease-requests.member.index') }}" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const assetSelect = document.getElementById('asset_id');
    const startDateInput = document.getElementById('start_date');
    const requestedDaysInput = document.getElementById('requested_days');
    const endDateInput = document.getElementById('end_date');
    const dailyRateInput = document.getElementById('daily_rate');
    const totalAmountInput = document.getElementById('total_amount');

    function calculateEndDate() {
        const startDate = startDateInput.value;
        const days = parseInt(requestedDaysInput.value) || 0;
        
        if (startDate && days > 0) {
            const start = new Date(startDate);
            const end = new Date(start.getTime() + (days * 24 * 60 * 60 * 1000));
            endDateInput.value = end.toISOString().split('T')[0];
        } else {
            endDateInput.value = '';
        }
    }

    function calculateTotalAmount() {
        const dailyRate = parseFloat(dailyRateInput.value) || 0;
        const days = parseInt(requestedDaysInput.value) || 0;
        const total = dailyRate * days;
        totalAmountInput.value = total.toFixed(2);
    }

    assetSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            dailyRateInput.value = selectedOption.dataset.dailyRate || '0';
            calculateTotalAmount();
        } else {
            dailyRateInput.value = '0';
            totalAmountInput.value = '0';
        }
    });

    requestedDaysInput.addEventListener('input', function() {
        calculateEndDate();
        calculateTotalAmount();
    });

    startDateInput.addEventListener('change', function() {
        calculateEndDate();
    });

    // Set minimum date to today
    startDateInput.min = new Date().toISOString().split('T')[0];
});
</script>
@endsection
