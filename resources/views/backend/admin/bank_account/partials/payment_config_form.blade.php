@if($paymentType)
<div class="payment-config-section">
    <h5 class="text-primary">{{ _lang('Payment Method Configuration') }}</h5>
    <p class="text-muted">{{ _lang('Configure the settings for') }} {{ ucfirst($paymentType) }}</p>
    
    @if($paymentType === 'paystack')
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Paystack Secret Key') }} <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="config[paystack_secret_key]" 
                           value="{{ old('config.paystack_secret_key') }}" required>
                    <small class="form-text text-muted">{{ _lang('Your Paystack secret key from the dashboard') }}</small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Paystack Public Key') }}</label>
                    <input type="text" class="form-control" name="config[paystack_public_key]" 
                           value="{{ old('config.paystack_public_key') }}">
                    <small class="form-text text-muted">{{ _lang('Your Paystack public key (optional)') }}</small>
                </div>
            </div>
        </div>
    @elseif($paymentType === 'buni')
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Buni Base URL') }} <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" name="config[buni_base_url]" 
                           value="{{ old('config.buni_base_url') }}" required
                           placeholder="https://api.buni.kcbgroup.com">
                    <small class="form-text text-muted">{{ _lang('The base URL for Buni API') }}</small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Client ID') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="config[buni_client_id]" 
                           value="{{ old('config.buni_client_id') }}" required>
                    <small class="form-text text-muted">{{ _lang('Your Buni client ID') }}</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Client Secret') }} <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="config[buni_client_secret]" 
                           value="{{ old('config.buni_client_secret') }}" required>
                    <small class="form-text text-muted">{{ _lang('Your Buni client secret') }}</small>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Company Code') }}</label>
                    <input type="text" class="form-control" name="config[company_code]" 
                           value="{{ old('config.company_code', 'KE0010001') }}">
                    <small class="form-text text-muted">{{ _lang('Your company code (default: KE0010001)') }}</small>
                </div>
            </div>
        </div>
    @elseif($paymentType === 'manual')
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="control-label">{{ _lang('Processing Instructions') }}</label>
                    <textarea class="form-control" name="config[processing_instructions]" rows="4" 
                              placeholder="{{ _lang('Instructions for manual processing of withdrawals...') }}">{{ old('config.processing_instructions') }}</textarea>
                    <small class="form-text text-muted">{{ _lang('Instructions for manual processing') }}</small>
                </div>
            </div>
        </div>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            {{ _lang('Manual processing means withdrawals will be queued for admin review and manual processing.') }}
        </div>
    @endif
</div>
@endif
