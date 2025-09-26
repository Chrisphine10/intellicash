@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h4 class="header-title">{{ _lang('Connect Payment Method') }}</h4>
                <p class="text-muted">{{ _lang('Connect a payment method to enable automated withdrawals for this bank account') }}</p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('bank_accounts.payment.connect.store', $bankAccount->id) }}" id="payment-connection-form">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Bank Account') }}</label>
                                <input type="text" class="form-control" value="{{ $bankAccount->bank_name }} - {{ $bankAccount->account_name }}" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Payment Method Type') }} <span class="text-danger">*</span></label>
                                <select class="form-control" name="payment_method_type" id="payment_method_type" required>
                                    <option value="">{{ _lang('Select Payment Method') }}</option>
                                    <option value="paystack" {{ old('payment_method_type') == 'paystack' ? 'selected' : '' }}>Paystack</option>
                                    <option value="buni" {{ old('payment_method_type') == 'buni' ? 'selected' : '' }}>KCB Buni</option>
                                    <option value="manual" {{ old('payment_method_type') == 'manual' ? 'selected' : '' }}>Manual Processing</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Payment Reference') }}</label>
                                <input type="text" class="form-control" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="Optional reference for this payment method">
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic configuration fields will be loaded here -->
                    <div id="payment-config-container">
                        <!-- Configuration fields will be loaded via AJAX -->
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <button type="button" class="btn btn-info" id="test-connection-btn" style="display: none;">
                                    <i class="fa fa-check"></i> {{ _lang('Test Connection') }}
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-link"></i> {{ _lang('Connect Payment Method') }}
                                </button>
                                <a href="{{ route('bank_accounts.index') }}" class="btn btn-secondary">
                                    <i class="fa fa-times"></i> {{ _lang('Cancel') }}
                                </a>
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
$(document).ready(function() {
    // Load configuration fields when payment method type changes
    $('#payment_method_type').on('change', function() {
        var paymentType = $(this).val();
        var container = $('#payment-config-container');
        
        if (paymentType) {
            // Show loading
            container.html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading configuration...</div>');
            
            // Load configuration form
            $.get('{{ route("bank_accounts.payment.config.form") }}', {
                payment_type: paymentType
            }, function(data) {
                container.html(data);
                $('#test-connection-btn').show();
            }).fail(function() {
                container.html('<div class="alert alert-danger">Failed to load configuration form</div>');
                $('#test-connection-btn').hide();
            });
        } else {
            container.html('');
            $('#test-connection-btn').hide();
        }
    });

    // Test connection
    $('#test-connection-btn').on('click', function() {
        var paymentType = $('#payment_method_type').val();
        var config = {};
        
        // Collect configuration data
        $('#payment-config-container input, #payment-config-container textarea, #payment-config-container select').each(function() {
            var name = $(this).attr('name');
            if (name && name.startsWith('config[')) {
                config[name.replace('config[', '').replace(']', '')] = $(this).val();
            }
        });

        var btn = $(this);
        var originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');

        $.post('{{ route("bank_accounts.payment.test", $bankAccount->id) }}', {
            payment_method_type: paymentType,
            config: config,
            _token: '{{ csrf_token() }}'
        }, function(response) {
            if (response.success) {
                showAlert('success', response.message);
            } else {
                showAlert('error', response.message);
            }
        }).fail(function() {
            showAlert('error', 'Connection test failed');
        }).always(function() {
            btn.prop('disabled', false).html(originalText);
        });
    });

    // Form submission
    $('#payment-connection-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = new FormData(this);
        
        // Add configuration data to form
        $('#payment-config-container input, #payment-config-container textarea, #payment-config-container select').each(function() {
            var name = $(this).attr('name');
            if (name && name.startsWith('config[')) {
                formData.append(name, $(this).val());
            }
        });

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success || response.redirect) {
                    window.location.href = '{{ route("bank_accounts.index") }}';
                } else {
                    showAlert('error', response.message || 'Failed to connect payment method');
                }
            },
            error: function(xhr) {
                var errors = xhr.responseJSON?.errors || {};
                var errorMessages = [];
                
                for (var field in errors) {
                    errorMessages.push(errors[field].join(', '));
                }
                
                showAlert('error', errorMessages.join('<br>') || 'An error occurred');
            }
        });
    });

    function showAlert(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span>' +
            '</button>' +
            '</div>';
        
        $('.card-body').prepend(alertHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
});
</script>
@endsection
