@extends('layouts.guest')

@section('content')
<div class="row">
	<div class="col-lg-6 offset-lg-3">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Pay Via Razorpay') }}</h4>
			</div>
			<div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table table-bordered">
                            <tr>
                                <td>{{ _lang('Package Name') }}</td>
                                <td>{{ $package->name }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang('Cost') }}</td>
                                <td>{{ decimalPlace($package->cost, currency_symbol()) }}</td>
                            </tr>
                            @if($package->discount > 0)
                            <tr>
                                <td>{{ _lang('Discount') }}</td>
                                <td>{{ $package->discount }}%</td>
                            </tr>
                            @endif
                            <tr>
                                <td>{{ _lang('Grand Total') }}</td>
                                <td>{{ decimalPlace($package->cost - ($package->discount / 100) * $package->cost, currency_symbol()) }}</td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <form action="{{ $data->callback_url }}?user_id={{ $data->user->id }}&slug={{ $slug }}" method="POST">
                                        @csrf
                                        <script
                                            src="https://checkout.razorpay.com/v1/checkout.js"
                                            data-key="{{ $gateway->parameters->razorpay_key_id }}"
                                            data-amount="{{ (($package->cost - ($package->discount / 100) * $package->cost) * 100) }}"
                                            data-currency="{{ get_option('currency', 'USD') }}"
                                            data-name="{{ _lang('Subscription Payment') }}"
                                            data-image="{{ get_logo() }}"
                                            data-description="{{ get_option('company_name').' '._lang('Subscription') }}"
                                            data-prefill.name="{{ $data->user->name }}"
                                            data-prefill.email="{{ $data->user->email  }}">
                                        </script>
                                    </form>
                                </td>
                            </tr>					
                        </table>
                    </div>
                </div>
			</div>
		</div>
    </div>
</div>
@endsection