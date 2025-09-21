@extends('layouts.guest')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Pay Via').' '.$gateway->name }}</h4>
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

                            @if($gateway->instructions != '')
                            <tr>
                                <td colspan="2">{!! xss_clean($gateway->instructions) !!}</td>
                            </tr>
                            @endif

                            @if($gateway->parameters)
                                <tr>
                                    <td colspan="2">
                                        <form action="{{ route('subscription_callback.offline',$gateway->slug) }}" method="post" class="validate" enctype="multipart/form-data">
                                            @csrf
                                            @foreach($gateway->parameters as $form_field)
                                            <div class="form-group">
                                                <label class="form-label">{{ $form_field->field_label }}</label>
                                                {!! xss_clean(generate_input_field_2($form_field)) !!}
                                            </div>
                                            @endforeach
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-primary btn-block">{{ _lang('Submit') }}</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
			</div>
		</div>
    </div>
</div>
@endsection