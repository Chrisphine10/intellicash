@extends('layouts.app')

@section('title', _lang('Receipt Verification'))

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">{{ _lang('Receipt Verification') }}</h4>
                </div>
                <div class="card-body">
                    @if($verification['valid'])
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>{{ _lang('Receipt Verified Successfully') }}</strong>
                        </div>

                        <div class="verification-details">
                            <h5>{{ _lang('Transaction Details') }}</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <td><strong>{{ _lang('Transaction ID') }}</strong></td>
                                        <td>{{ $verification['transaction']['id'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ _lang('Amount') }}</strong></td>
                                        <td>{{ decimalPlace($verification['transaction']['amount'], currency($verification['transaction']['currency'] ?? get_base_currency())) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ _lang('Type') }}</strong></td>
                                        <td>{{ ucwords(str_replace('_', ' ', $verification['transaction']['type'])) }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ _lang('Status') }}</strong></td>
                                        <td>
                                            @if($verification['transaction']['status'] === 'approved')
                                                <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                            @elseif($verification['transaction']['status'] === 'pending')
                                                <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                            @else
                                                <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ _lang('Member') }}</strong></td>
                                        <td>{{ $verification['transaction']['member_name'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ _lang('Account Number') }}</strong></td>
                                        <td>{{ $verification['transaction']['account_number'] }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>{{ _lang('Date') }}</strong></td>
                                        <td>{{ \Carbon\Carbon::parse($verification['transaction']['created_at'])->format(get_date_format() . ' ' . get_time_format()) }}</td>
                                    </tr>
                                    @if(!empty($verification['transaction']['description']))
                                    <tr>
                                        <td><strong>{{ _lang('Description') }}</strong></td>
                                        <td>{{ $verification['transaction']['description'] }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            @if(isset($verification['verification_data']['ethereum_tx']) && $verification['verification_data']['ethereum_tx'])
                            <div class="ethereum-verification mt-4">
                                <h6>{{ _lang('Blockchain Verification') }}</h6>
                                <p class="text-muted">
                                    <i class="fab fa-ethereum"></i>
                                    {{ _lang('This receipt has been verified on the Ethereum blockchain') }}
                                </p>
                                <small class="text-muted">
                                    {{ _lang('Ethereum Transaction') }}: 
                                    <code>{{ $verification['verification_data']['ethereum_tx'] }}</code>
                                </small>
                            </div>
                            @endif

                            <div class="verification-meta mt-4">
                                <h6>{{ _lang('Verification Information') }}</h6>
                                <ul class="list-unstyled">
                                    <li><strong>{{ _lang('Verification Token') }}:</strong> <code>{{ $token }}</code></li>
                                    <li><strong>{{ _lang('Transaction Hash') }}:</strong> <code>{{ $verification['verification_data']['tx_hash'] }}</code></li>
                                    <li><strong>{{ _lang('Verified At') }}:</strong> {{ now()->format(get_date_format() . ' ' . get_time_format()) }}</li>
                                </ul>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>{{ _lang('Verification Failed') }}</strong>
                        </div>

                        <div class="error-details">
                            <p>{{ $verification['error'] ?? _lang('Unknown error occurred during verification') }}</p>
                            
                            <div class="mt-4">
                                <h6>{{ _lang('Possible Reasons') }}:</h6>
                                <ul>
                                    <li>{{ _lang('The QR code may be invalid or corrupted') }}</li>
                                    <li>{{ _lang('The receipt may have been modified') }}</li>
                                    <li>{{ _lang('The verification token may have expired') }}</li>
                                    <li>{{ _lang('The transaction may not exist in our system') }}</li>
                                </ul>
                            </div>

                            <div class="mt-4">
                                <a href="{{ route('home') }}" class="btn btn-primary">
                                    <i class="fas fa-home"></i> {{ _lang('Go Home') }}
                                </a>
                                <button onclick="window.history.back()" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> {{ _lang('Go Back') }}
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.verification-details {
    margin-top: 20px;
}

.ethereum-verification {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

.verification-meta {
    background-color: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
}

.verification-meta code {
    background-color: #e9ecef;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.9em;
    word-break: break-all;
}

.error-details {
    margin-top: 20px;
}

.error-details ul {
    margin-top: 10px;
}

.error-details li {
    margin-bottom: 5px;
}
</style>
@endsection
