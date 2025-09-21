@extends('layouts.guest')

@section('content')
<div class="row">
   <div class="col-lg-6 offset-lg-3">
      <div class="card">
         <div class="card-header">
            <h4 class="header-title text-center">{{ _lang('Pay Via Flutterwave') }}</h4>
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
							<form>
								<script src="https://checkout.flutterwave.com/v3.js"></script>
								<button type="button" class="btn btn-primary btn-block" onClick="makePayment()">{{ _lang('Pay Now') }}</button>
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

@section('js-script')
<script>
  function makePayment() {
    FlutterwaveCheckout({
      public_key: "{{ $gateway->parameters->public_key }}",
      tx_ref: "{{ uniqid().time() }}",
      amount: {{ ($package->cost - ($package->discount / 100) * $package->cost) }},
      currency: "{{ get_option('currency', 'USD') }}",
      customer: {
        name: "{{ $data->user->name }}",
        email: "{{ $data->user->email }}",
        phone_number: "{{ $data->user->mobile }}",
      },
      callback: function (data) {
        if(data.status == 'successful'){
            window.location.href = "{{ $data->callback_url }}?transaction_id=" + data.transaction_id + "&user_id={{ $data->user->id }}&slug={{ $slug }}";
        }else{
            window.location.href = "{{ $data->callback_url }}?user_id={{ $data->user->id }}";
        }
      },
      onclose: function() {},
      customizations: {
        title: "{{ _lang('Subscription Payment') }}",
        description: "{{ get_option('company_name').' '._lang('Subscription') }}",
        logo: "{{ get_logo() }}",
      },
    });
  }
</script>
@endsection