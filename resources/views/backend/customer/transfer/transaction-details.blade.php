@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
		    <div class="card-header d-flex justify-content-between align-items-center">
				<div class="header-title">{{ _lang('Transaction Details') }}</div>

				<div class="dropdown">
					<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
						<i class="fas fa-print mr-2"></i>{{ _lang('Print Receipt') }}
					</button>
					<div class="dropdown-menu">
						<a class="dropdown-item print print-1" href="#" data-print="receipt" data-title="{{ _lang('Transaction Receipt') }}"><i class="fas fa-print mr-2"></i>{{ _lang('Print') }}</a>
						<a class="dropdown-item print print-2" href="#" data-print="pos-receipt" data-title="{{ _lang('Transaction Receipt') }}"><i class="fas fa-print mr-2"></i>{{ _lang('POS Print') }}</a>
					</div>
				</div>
			</div>
			
			<div class="card-body">
			    <table class="table table-bordered">
				    <tr><td>{{ _lang('Date') }}</td><td>{{ $transaction->trans_date }}</td></tr>
					<tr><td>{{ _lang('Member') }}</td><td>{{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
					<tr><td>{{ _lang('Account Number') }}</td><td>{{ $transaction->bankAccount->account_number ?? $transaction->account->account_number }}</td></tr>
					<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($transaction->amount, currency($transaction->bankAccount->currency->name ?? $transaction->account->savings_type->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Debit/Credit') }}</td><td>{{ strtoupper($transaction->dr_cr) }}</td></tr>
					<tr><td>{{ _lang('Type') }}</td><td>{{ ucwords(str_replace('_', ' ', $transaction->type)) }}</td></tr>
					<tr><td>{{ _lang('Method') }}</td><td>{{ $transaction->method }}</td></tr>
					<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($transaction->status)) !!}</td></tr>
					<tr><td>{{ _lang('Note') }}</td><td>{{ $transaction->note }}</td></tr>
					<tr><td>{{ _lang('Description') }}</td><td>{{ $transaction->description }}</td></tr>
					<tr><td>{{ _lang('Created By') }}</td><td>{{ $transaction->created_by->name }} ({{ $transaction->created_at }})</td></tr>
					<tr><td>{{ _lang('Updated By') }}</td><td>{{ $transaction->updated_by->name }} ({{ $transaction->updated_at }})</td></tr>
			    </table>

				<div id="pos-receipt" class="print-only">
					<div class="pos-print">
						<div class="receipt-header">
							<h4>{{ get_tenant_option('business_name', request()->tenant->name) }}</h4>
							<p>{{ _lang('Transaction Receipt') }}</p>
							<p>{{ get_tenant_option('address') }}</p>
							<p>{{ get_tenant_option('email') }}, {{ get_tenant_option('phone') }}</p>
							<p>{{ _lang('Print Date').': '.date(get_date_format()) }}</p>
						</div>

						<table class="mt-4 mx-auto">
							<tr><td>{{ _lang('Date') }}</td><td>: {{ $transaction->trans_date }}</td></tr>
							<tr><td>{{ _lang('Member') }}</td><td>: {{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
							<tr><td>{{ _lang('Account Number') }}</td><td>: {{ $transaction->bankAccount->account_number ?? $transaction->account->account_number }}</td></tr>
							<tr><td>{{ _lang('Amount') }}</td><td>: {{ decimalPlace($transaction->amount, currency($transaction->bankAccount->currency->name ?? $transaction->account->savings_type->currency->name)) }}</td></tr>
							<tr><td>{{ _lang('Debit/Credit') }}</td><td>: {{ strtoupper($transaction->dr_cr) }}</td></tr>
							<tr><td>{{ _lang('Type') }}</td><td>: {{ ucwords(str_replace('_', ' ', $transaction->type)) }}</td></tr>
							<tr><td>{{ _lang('Method') }}</td><td>: {{ $transaction->method }}</td></tr>
							<tr><td>{{ _lang('Status') }}</td><td>: {!! xss_clean(transaction_status($transaction->status, false)) !!}</td></tr>
							<tr><td>{{ _lang('Note') }}</td><td>: {{ $transaction->note ?? _lang('N/A') }}</td></tr>
							<tr><td>{{ _lang('Description') }}</td><td>: {{ $transaction->description }}</td></tr>
							<tr><td>{{ _lang('Created By') }}</td><td>: {{ $transaction->created_by->name }}</td></tr>
							<tr><td>{{ _lang('Created At') }}</td><td>: {{ $transaction->created_at }}</td></tr>
						</table>

						<!-- QR Code Section for POS Receipt - Only show if QR code module is enabled -->
						@if(app('tenant')->isQrCodeEnabled())
						<div class="qr-code-section text-center mt-3">
							<div class="qr-code-container">
								<img id="pos-receipt-qr-code" src="" alt="Receipt QR Code" class="qr-code-image" style="max-width: 150px; height: auto;">
							</div>
							<p class="qr-code-text mt-1">
								<small>{{ _lang('Scan to verify') }}</small>
							</p>
						</div>
						@endif
					</div>
				</div>

				<div id="receipt" class="print-only">
					<div class="receipt-header text-center">
						<img src="{{ get_logo() }}" class="logo" alt="logo"/>
						<p>{{ _lang('Transaction Receipt') }}</p>
						<p>{{ get_tenant_option('address') }}</p>
						<p>{{ get_tenant_option('email') }}, {{ get_tenant_option('phone') }}</p>
						<p>{{ _lang('Print Date').': '.date(get_date_format()) }}</p>
					</div>

					<table class="table table-bordered mt-4 mx-auto">
						<tr><td>{{ _lang('Date') }}</td><td>{{ $transaction->trans_date }}</td></tr>
						<tr><td>{{ _lang('Member') }}</td><td>{{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
						<tr><td>{{ _lang('Account Number') }}</td><td>{{ $transaction->bankAccount->account_number ?? $transaction->account->account_number }}</td></tr>
						<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($transaction->amount, currency($transaction->bankAccount->currency->name ?? $transaction->account->savings_type->currency->name)) }}</td></tr>
						<tr><td>{{ _lang('Debit/Credit') }}</td><td>{{ strtoupper($transaction->dr_cr) }}</td></tr>
						<tr><td>{{ _lang('Type') }}</td><td>{{ str_replace('_', ' ', $transaction->type) }}</td></tr>
						<tr><td>{{ _lang('Method') }}</td><td>{{ $transaction->method }}</td></tr>
						<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($transaction->status, false)) !!}</td></tr>
						<tr><td>{{ _lang('Note') }}</td><td>{{ $transaction->note ?? _lang('N/A') }}</td></tr>
						<tr><td>{{ _lang('Description') }}</td><td>{{ $transaction->description }}</td></tr>
						<tr><td>{{ _lang('Created By') }}</td><td>{{ $transaction->created_by->name }}</td></tr>
						<tr><td>{{ _lang('Created At') }}</td><td>{{ $transaction->created_at }}</td></tr>
					</table>

        <!-- QR Code Section - Only show if QR code module is enabled -->
        @if(app('tenant')->isQrCodeEnabled())
        <div class="qr-code-section text-center mt-4">
            <div class="qr-code-container">
                @php
                    try {
                        $qrService = app(\App\Services\ReceiptQrService::class);
                        $qrCodeData = $qrService->generateQrData($transaction);
                        $qrCodeImage = $qrService->generateQrCode($transaction);
                    } catch (Exception $e) {
                        $qrCodeData = null;
                        $qrCodeImage = null;
                    }
                @endphp
                
                @if($qrCodeImage)
                    <img src="{{ $qrCodeImage }}" alt="Receipt QR Code" class="qr-code-image" style="max-width: 200px; height: auto;">
                    <p class="qr-code-text mt-2">
                        <small>{{ _lang('Scan QR code to verify this receipt') }}</small>
                    </p>
                    <p class="verification-url mt-1">
                        <small class="text-muted">{{ $qrCodeData['verification_url'] ?? '' }}</small>
                    </p>
                @else
                    <p class="text-muted">{{ _lang('QR Code not available') }}</p>
                @endif
            </div>
        </div>
        @endif
				</div>
			</div>
	    </div>
	</div>
</div>
@endsection

@section('js-script')
<script>
$(function() {
	"use strict";

	// QR code is now generated server-side, no AJAX needed

	let params = new URLSearchParams(window.location.search);
    let value = params.get("print");

	if(value === 'general'){
		document.title = $('.print-1').data('title') ?? document.title;
		$('body').html($("#receipt").clone());
		window.print();
		setTimeout(function () {
			window.close();
		}, 300);
	}else if(value === 'pos'){
		document.title = $('.print-2').data('title') ?? document.title;
		$('body').html($("#pos-receipt").html());
		window.print();
		setTimeout(function () {
			window.close();
		}, 300);
	}

});
</script>
@endsection