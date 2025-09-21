@extends('layouts.guest')

@section('content')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header text-center">
				<span class="panel-title">{{ _lang('Pending Payment') }}</span>
            </div>

            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-12">
                        <div class="alert alert-warning text-center">
                            <h5><i class="fas fa-exclamation-circle"></i> {{ _lang('Your Payment is Under Review') }}</h5>
                            <p>{{ _lang('Your transaction is currently being processed. You will be notified once it is approved.') }}</p>
                        </div>

                        @if($pendingPayments->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>{{ _lang('Order ID') }}</th>
                                            <th>{{ _lang('Method') }}</th>
                                            <th>{{ _lang('Amount') }}</th>
                                            <th>{{ _lang('Payment Date') }}</th>
                                            <th>{{ _lang('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingPayments as $payment)
                                            <tr>
                                                <td>{{ $payment->order_id }}</td>
                                                <td>{{ $payment->payment_method }}</td>
                                                <td>{{ decimalPlace($payment->amount, currency_symbol()) }}</td>
                                                <td>{{ $payment->created_at }}</td>
                                                <td><span class="badge badge-warning">{{ _lang('Pending') }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-center">{{ _lang('No pending payments found.') }}</p>
                        @endif

                        <div class="text-center mt-3">
                            <a href="{{ url('/contact') }}" class="btn btn-outline-primary">
                                <i class="fas fa-headset"></i> {{ _lang('Contact Support') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
