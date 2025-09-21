@extends('layouts.guest')

@section('content')
<div class="row">
	<div class="col-lg-6 offset-lg-3">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Pay Via Paystack') }}</h4>
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
                        <button type="button" class="pay-now-btn" onclick="payWithPaystack()"> {{ _lang('Pay Now') }}</button>
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
<script src="https://js.paystack.co/v1/inline.js"></script>

<script type="text/javascript">

function payWithPaystack(e) {
  let handler = PaystackPop.setup({
    key: '{{ $gateway->parameters->paystack_public_key }}',
    email: '{{ $data->user->email }}',
    amount: {{ (($package->cost - ($package->discount / 100) * $package->cost) * 100) }},
    currency: '{{ get_option('currency', 'USD') }}',
    firstname: '{{ $data->user->name }}',
    ref: '{{ uniqid().time() }}',
    callback: function(response){
    	window.location = "{{ $data->callback_url }}?reference=" + response.reference + "&user_id={{ $data->user->id }}&slug={{ $slug }}";
    }
  });
  handler.openIframe();
}

</script>
@endsection