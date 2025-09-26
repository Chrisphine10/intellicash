@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="header-title">{{ _lang('New Payment Method') }}</span>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('payment_methods.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Name') }}</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Type') }}</label>
                                <select class="form-control auto-select" data-selected="{{ old('type') }}" name="type" id="payment_type" required>
                                    <option value="">{{ _lang('Select One') }}</option>
                                    <option value="paystack">{{ _lang('Paystack') }}</option>
                                    <option value="buni">{{ _lang('Buni') }}</option>
                                    <option value="manual">{{ _lang('Manual') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Currency') }}</label>
                                <select class="form-control auto-select" data-selected="{{ old('currency_id') }}" name="currency_id" required>
                                    <option value="">{{ _lang('Select One') }}</option>
                                    @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->full_name }} ({{ $currency->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Description') }}</label>
                                <textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Status') }}</label>
                                <select class="form-control auto-select" data-selected="{{ old('is_active', 1) }}" name="is_active">
                                    <option value="1">{{ _lang('Active') }}</option>
                                    <option value="0">{{ _lang('Inactive') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div id="config-section" style="display: none;">
                                <h5>{{ _lang('Configuration') }}</h5>
                                <div id="config-fields">
                                    <!-- Configuration fields will be loaded here -->
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12 mt-4 form-group mb-0">
                            <button type="submit" class="btn btn-primary">{{ _lang('Save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#payment_type').change(function() {
        var paymentType = $(this).val();
        
        if (paymentType) {
            $.ajax({
                url: "{{ route('payment_methods.config.form') }}",
                type: 'GET',
                data: {
                    payment_type: paymentType
                },
                success: function(response) {
                    $('#config-fields').html(response);
                    $('#config-section').show();
                }
            });
        } else {
            $('#config-section').hide();
            $('#config-fields').empty();
        }
    });
});
</script>
@endsection
