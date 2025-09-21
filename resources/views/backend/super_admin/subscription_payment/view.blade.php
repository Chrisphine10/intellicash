@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
		    <div class="card-header">
				<span class="panel-title">{{ _lang('Payment Details') }}</span>
			</div>

			<div class="card-body">
			    <table class="table table-bordered">
                    <tr><td>{{ _lang('Tenant Name') }}</td><td>{{ $subscription_payment->tenant->name }}</td></tr>
                    <tr><td>{{ _lang('Subscription Plan') }}</td><td>{{ $subscription_payment->package->name }}  ({{ ucwords($subscription_payment->package->package_type) }})</td></tr>
					<tr><td>{{ _lang('Method') }}</td><td>{{ $subscription_payment->payment_method }}</td></tr>
					<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($subscription_payment->amount, currency_symbol()) }}</td></tr>

                    @if($subscription_payment->extra)
                        @foreach($subscription_payment->extra as $key => $value)
                            <tr>
                                <td><b>{{ $value->field_label }}</b></td>
                                <td>
                                    @if($value->field_type != 'file')
                                    {{ $value->field_value }}
                                    @else
                                    <a href="{{ asset('/public/uploads/media/'. $value->field_value) }}" class="btn btn-outline-primary btn-xs" target="_blank"><i class="fas fa-paperclip mr-1"></i>{{ _lang('Preview') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
					<tr>
                        <td>{{ _lang('Status') }}</td>
                        <td>
                            @if($subscription_payment->status == 0)
                                {!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
                            @elseif($subscription_payment->status == 1)
                                {!! xss_clean(show_status(_lang('Completed'), 'success')) !!}
                            @elseif($subscription_payment->status == 2)
                                {!! xss_clean(show_status(_lang('Hold'), 'warning')) !!}
                            @elseif($subscription_payment->status == 3)
                                {!! xss_clean(show_status(_lang('Refunded'), 'danger')) !!}
                            @elseif($subscription_payment->status == 4)
                                {!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
                            @endif
                        </td>
                    </tr>
                    @if($subscription_payment->status == 0)
                    <tr>
                        <td>{{ _lang('Action') }}</td>
                        <td>
                            <a href="{{ route('admin.subscription_payments.approve_payment_requests', $subscription_payment->id) }}" class="btn btn-primary btn-xs"><i class="fas fa-check-circle mr-1"></i>{{ _lang('Approved') }}</a>
                            <a href="{{ route('admin.subscription_payments.reject_payment_requests', $subscription_payment->id) }}" class="btn btn-danger btn-xs ajax-modal" data-title="{{ _lang('Rejection Reason') }}"><i class="fas fa-times-circle mr-1"></i>{{ _lang('Reject') }}</a>
                        </td>
                    </tr>
                    @endif
			    </table>
			</div>
	    </div>
	</div>
</div>
@endsection