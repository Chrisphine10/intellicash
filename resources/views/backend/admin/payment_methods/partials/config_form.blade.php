@if($paymentType == 'paystack')
    <div class="form-group">
        <label class="control-label">{{ _lang('Paystack Secret Key') }}</label>
        <input type="password" class="form-control" name="config[paystack_secret_key]" required>
        <small class="form-text text-muted">{{ _lang('Your Paystack secret key from the dashboard') }}</small>
    </div>
    
    <div class="form-group">
        <label class="control-label">{{ _lang('Paystack Public Key') }}</label>
        <input type="text" class="form-control" name="config[paystack_public_key]">
        <small class="form-text text-muted">{{ _lang('Your Paystack public key (optional)') }}</small>
    </div>
@elseif($paymentType == 'buni')
    <div class="form-group">
        <label class="control-label">{{ _lang('Buni Base URL') }}</label>
        <input type="url" class="form-control" name="config[buni_base_url]" required>
        <small class="form-text text-muted">{{ _lang('The base URL for Buni API') }}</small>
    </div>
    
    <div class="form-group">
        <label class="control-label">{{ _lang('Client ID') }}</label>
        <input type="text" class="form-control" name="config[buni_client_id]" required>
        <small class="form-text text-muted">{{ _lang('Your Buni client ID') }}</small>
    </div>
    
    <div class="form-group">
        <label class="control-label">{{ _lang('Client Secret') }}</label>
        <input type="password" class="form-control" name="config[buni_client_secret]" required>
        <small class="form-text text-muted">{{ _lang('Your Buni client secret') }}</small>
    </div>
    
    <div class="form-group">
        <label class="control-label">{{ _lang('Company Code') }}</label>
        <input type="text" class="form-control" name="config[company_code]" value="KE0010001">
        <small class="form-text text-muted">{{ _lang('Your company code (default: KE0010001)') }}</small>
    </div>
@elseif($paymentType == 'manual')
    <div class="form-group">
        <label class="control-label">{{ _lang('Processing Instructions') }}</label>
        <textarea class="form-control" name="config[processing_instructions]" rows="3"></textarea>
        <small class="form-text text-muted">{{ _lang('Instructions for manual processing') }}</small>
    </div>
@endif
