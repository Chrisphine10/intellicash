@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Update Payment Gateway') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('admin.payment_gateways.update', $id) }}" enctype="multipart/form-data">
					@csrf
					<input name="_method" type="hidden" value="PATCH">

					<div class="form-group row">
						<label class="col-xl-3 col-form-label">{{ _lang('Name') }}</label>
						<div class="col-xl-9">
							<input type="text" class="form-control" value="{{ $paymentgateway->name }}" readonly>
						</div>
					</div>

					<div class="form-group row">
						<label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>
						<div class="col-xl-9">
							<select class="form-control auto-select" data-selected="{{ $paymentgateway->status }}" name="status" id="gateway_status" required>
								<option value="0">{{ _lang('Disable') }}</option>
								<option value="1">{{ _lang('Enable') }}</option>
							</select>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">				
							@foreach($paymentgateway->parameters as $key => $value)
								@if($key != 'environment')
									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ strtoupper(str_replace('_',' ',$key)) }}</label>
										<div class="col-xl-9">
											<input type="text" class="form-control" value="{{ $value }}" name="parameter_value[{{$key}}]">
										</div>
									</div>
								@else
									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ strtoupper(str_replace('_',' ',$key)) }}</label>
										<div class="col-xl-9">
											<select class="form-control auto-select" data-selected="{{ $value }}" name="parameter_value[{{$key}}]">
												<option value="sandbox">{{ _lang('Sandbox') }}</option>
												<option value="live">{{ _lang('Live') }}</option>
											</select>
										</div>
									</div>
								@endif
							@endforeach

							<div class="form-group row">
                                <div class="col-xl-9 offset-xl-3">
                                    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
                                </div>
                            </div>
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
(function ($) {
  "use strict";

	@if($paymentgateway->is_crypto == 0)

    $('#gateway_currency').val() != '' ? $(".gateway_currency_preview").html($('#gateway_currency').val()) : '';

    $(document).on('change','#gateway_currency', function(){
		$(".gateway_currency_preview").html($(this).val());
	});
	
	@endif

})(jQuery);
</script>
@endsection



