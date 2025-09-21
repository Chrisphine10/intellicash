@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-6 offset-lg-3">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Payment Confirm') }}</h4>
			</div>
			<div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Amount') }}</label>
                            <input type="text" class="form-control" name="amount" value="{{ decimalPlace($gatewayAmount - $charge, currency($deposit->gateway->currency)) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Charge') }}</label>
                            <input type="text" class="form-control" name="charge" value="{{ decimalPlace($charge, currency($deposit->gateway->currency)) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Total') }}</label>
                            <input type="text" class="form-control" name="total" value="{{ decimalPlace($gatewayAmount, currency($deposit->gateway->currency)) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Customer Reference') }}</label>
                            <input type="text" class="form-control" name="customer_reference" value="{{ 'INV-' . $deposit->id . '-' . time() }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Payment Instructions') }}</label>
                            <div class="alert alert-info">
                                <p><strong>{{ _lang('Please follow these steps to complete your payment:') }}</strong></p>
                                <ol>
                                    <li>{{ _lang('Open your mobile banking app or visit KCB Bank') }}</li>
                                    <li>{{ _lang('Select "Pay Bill" or "Buy Goods and Services"') }}</li>
                                    <li>{{ _lang('Enter the Till Number:') }} <strong>{{ $deposit->gateway->parameters->till_number ?? 'N/A' }}</strong></li>
                                    <li>{{ _lang('Enter the Amount:') }} <strong>{{ decimalPlace($gatewayAmount, currency($deposit->gateway->currency)) }}</strong></li>
                                    <li>{{ _lang('Enter the Customer Reference:') }} <strong>{{ 'INV-' . $deposit->id . '-' . time() }}</strong></li>
                                    <li>{{ _lang('Complete the payment') }}</li>
                                </ol>
                                <p class="mb-0"><small>{{ _lang('Your payment will be automatically verified once completed.') }}</small></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <button type="button" class="btn btn-primary btn-block" onclick="initiateBuniPayment()"> {{ _lang('Initiate Payment') }}</button>
                    </div>
                </div>
            </div>
	    </div>
    </div>
</div>
@endsection

@section('js-script')
<script type="text/javascript">

function initiateBuniPayment() {
    // Show loading state
    const button = document.querySelector('button[onclick="initiateBuniPayment()"]');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> {{ _lang("Processing...") }}';
    button.disabled = true;

    // Make API call to initiate payment
    fetch('{{ route("gateway.buni.initiate", request()->tenant->slug) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            deposit_id: {{ $deposit->id }},
            amount: {{ $gatewayAmount }},
            customer_reference: 'INV-{{ $deposit->id }}-{{ time() }}',
            customer_name: '{{ $deposit->member->first_name }} {{ $deposit->member->last_name }}',
            customer_mobile: '{{ $deposit->member->mobile }}',
            currency: '{{ $deposit->gateway->currency }}'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message and redirect
            alert('{{ _lang("Payment initiated successfully! Please complete the payment using your mobile banking app.") }}');
            window.location.href = '{{ route("dashboard.index") }}';
        } else {
            alert('{{ _lang("Payment initiation failed: ") }}' + data.message);
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('{{ _lang("An error occurred while initiating payment.") }}');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

</script>
@endsection
