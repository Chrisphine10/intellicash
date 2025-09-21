@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('QR Code Module Configuration') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('modules.qr_code.guide') }}" class="btn btn-outline-primary btn-sm me-2">
                        <i class="fas fa-book"></i> {{ _lang('View Guide') }}
                    </a>
                    <a href="{{ route('modules.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Modules') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('modules.qr_code.update') }}" method="POST" id="qr-code-config-form">
                    @csrf
                    
                    <!-- Basic Settings -->
                    <div class="row">
                        <div class="col-lg-12">
                            <h5 class="mb-3">{{ _lang('Basic Settings') }}</h5>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Enable QR Code Module') }}</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" value="1" {{ $qrCodeSettings->enabled ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="enabled">{{ _lang('Enable QR code generation for receipts') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Auto Generate QR Codes') }}</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="auto_generate_qr" name="auto_generate_qr" value="1" {{ $qrCodeSettings->auto_generate_qr ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="auto_generate_qr">{{ _lang('Automatically generate QR codes for new transactions') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Settings -->
                    <div class="row">
                        <div class="col-lg-12">
                            <h5 class="mb-3 mt-4">{{ _lang('QR Code Settings') }}</h5>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('QR Code Size') }}</label>
                                <input type="number" class="form-control" name="qr_code_size" value="{{ $qrCodeSettings->qr_code_size ?? 200 }}" min="100" max="500">
                                <small class="form-text text-muted">{{ _lang('Size in pixels (100-500)') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Error Correction Level') }}</label>
                                <select class="form-control" name="qr_code_error_correction">
                                    <option value="L" {{ ($qrCodeSettings->qr_code_error_correction ?? 'H') == 'L' ? 'selected' : '' }}>{{ _lang('Low (L)') }}</option>
                                    <option value="M" {{ ($qrCodeSettings->qr_code_error_correction ?? 'H') == 'M' ? 'selected' : '' }}>{{ _lang('Medium (M)') }}</option>
                                    <option value="Q" {{ ($qrCodeSettings->qr_code_error_correction ?? 'H') == 'Q' ? 'selected' : '' }}>{{ _lang('Quartile (Q)') }}</option>
                                    <option value="H" {{ ($qrCodeSettings->qr_code_error_correction ?? 'H') == 'H' ? 'selected' : '' }}>{{ _lang('High (H)') }}</option>
                                </select>
                                <small class="form-text text-muted">{{ _lang('Higher levels provide better error correction but larger codes') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Verification Cache Days') }}</label>
                                <input type="number" class="form-control" name="verification_cache_days" value="{{ $qrCodeSettings->verification_cache_days ?? 30 }}" min="1" max="365">
                                <small class="form-text text-muted">{{ _lang('How long to cache verification data (1-365 days)') }}</small>
                            </div>
                        </div>
                    </div>

                    <!-- Ethereum Blockchain Settings -->
                    <div class="row">
                        <div class="col-lg-12">
                            <h5 class="mb-3 mt-4">{{ _lang('Blockchain Integration') }}</h5>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Enable Ethereum Integration') }}</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="ethereum_enabled" name="ethereum_enabled" value="1" {{ $qrCodeSettings->ethereum_enabled ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="ethereum_enabled">{{ _lang('Store transaction hashes on Ethereum blockchain') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Include Blockchain Verification') }}</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="include_blockchain_verification" name="include_blockchain_verification" value="1" {{ $qrCodeSettings->include_blockchain_verification ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="include_blockchain_verification">{{ _lang('Include blockchain verification in QR codes') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ethereum Network Configuration -->
                    <div id="ethereum-settings" style="{{ $qrCodeSettings->ethereum_enabled ? '' : 'display: none;' }}">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Ethereum Network') }}</label>
                                    <select class="form-control" name="ethereum_network" id="ethereum_network">
                                        @foreach($networks as $key => $network)
                                        <option value="{{ $key }}" {{ ($qrCodeSettings->ethereum_network ?? 'mainnet') == $key ? 'selected' : '' }}>
                                            {{ $network['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('RPC URL') }}</label>
                                <input type="url" class="form-control" name="ethereum_rpc_url" value="{{ $qrCodeSettings->ethereum_rpc_url ?? '' }}" placeholder="https://mainnet.infura.io/v3/your-project-id">
                                <small class="form-text text-muted">{{ _lang('Ethereum RPC endpoint URL (optional)') }}</small>
                            </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Contract Address') }}</label>
                                <input type="text" class="form-control" name="ethereum_contract_address" value="{{ $qrCodeSettings->ethereum_contract_address ?? '' }}" placeholder="0x...">
                                <small class="form-text text-muted">{{ _lang('Smart contract address for storing transaction hashes (optional)') }}</small>
                            </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Account Address') }}</label>
                                    <input type="text" class="form-control" name="ethereum_account_address" value="{{ $qrCodeSettings->ethereum_account_address ?? '' }}" placeholder="0x...">
                                    <small class="form-text text-muted">{{ _lang('Ethereum account address for signing transactions (optional)') }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Private Key') }}</label>
                                <input type="password" class="form-control" name="ethereum_private_key" value="{{ $qrCodeSettings->ethereum_private_key ?? '' }}" placeholder="0x...">
                                <small class="form-text text-muted">{{ _lang('Private key for signing transactions (will be encrypted) (optional)') }}</small>
                            </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-info btn-sm" id="test-connection">
                                            <i class="fas fa-plug"></i> {{ _lang('Test Connection') }}
                                        </button>
                                        <small class="form-text text-muted">{{ _lang('Test your Ethereum network connection') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Network Information -->
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> {{ _lang('Network Information') }}</h6>
                                    <div id="network-info">
                                        <p><strong>{{ _lang('Network') }}:</strong> <span id="network-name">-</span></p>
                                        <p><strong>{{ _lang('Chain ID') }}:</strong> <span id="chain-id">-</span></p>
                                        <p><strong>{{ _lang('Explorer') }}:</strong> <span id="explorer-url">-</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Save Configuration') }}
                                </button>
                                <a href="{{ route('modules.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> {{ _lang('Cancel') }}
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
    // Toggle Ethereum settings visibility
    $('#ethereum_enabled').change(function() {
        if ($(this).is(':checked')) {
            $('#ethereum-settings').show();
        } else {
            $('#ethereum-settings').hide();
        }
    });

    // Update network information
    function updateNetworkInfo() {
        var network = $('#ethereum_network').val();
        var networks = @json($networks);
        
        if (networks[network]) {
            $('#network-name').text(networks[network].name);
            $('#chain-id').text(networks[network].chain_id);
            $('#explorer-url').html('<a href="' + networks[network].explorer + '" target="_blank">' + networks[network].explorer + '</a>');
        }
    }

    // Update network info on change
    $('#ethereum_network').change(updateNetworkInfo);
    updateNetworkInfo();

    // Test Ethereum connection
    $('#test-connection').click(function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ _lang("Testing...") }}');
        
        $.ajax({
            url: '{{ route("modules.qr_code.test_ethereum") }}',
            method: 'POST',
            data: {
                ethereum_network: $('#ethereum_network').val(),
                ethereum_rpc_url: $('input[name="ethereum_rpc_url"]').val(),
                ethereum_account_address: $('input[name="ethereum_account_address"]').val(),
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.result === 'success') {
                    alert('{{ _lang("Connection successful!") }}\n' + response.message + '\n{{ _lang("Block Number") }}: ' + response.block_number);
                } else {
                    alert('{{ _lang("Connection failed!") }}\n' + response.message);
                }
            },
            error: function() {
                alert('{{ _lang("Connection test failed!") }}');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    // Form validation
    $('#qr-code-config-form').submit(function(e) {
        var ethereumEnabled = $('#ethereum_enabled').is(':checked');
        
        if (ethereumEnabled) {
            var rpcUrl = $('input[name="ethereum_rpc_url"]').val();
            var contractAddress = $('input[name="ethereum_contract_address"]').val();
            var accountAddress = $('input[name="ethereum_account_address"]').val();
            var privateKey = $('input[name="ethereum_private_key"]').val();
            
            if (!rpcUrl || !contractAddress || !accountAddress || !privateKey) {
                e.preventDefault();
                alert('{{ _lang("Please fill in all Ethereum configuration fields when blockchain integration is enabled.") }}');
                return false;
            }
        } else {
            // Clear Ethereum fields when disabled
            $('input[name="ethereum_rpc_url"]').val('');
            $('input[name="ethereum_contract_address"]').val('');
            $('input[name="ethereum_account_address"]').val('');
            $('input[name="ethereum_private_key"]').val('');
        }
    });
});
</script>
@endsection
