@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-management.dashboard') }}">{{ _lang('Asset Management') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('assets.index', ['tenant' => app('tenant')->slug]) }}">{{ _lang('Assets') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Sell Asset') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Sell Asset') }}: {{ $asset->name }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Sale Information') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('assets.process-sale', ['tenant' => app('tenant')->slug, 'asset' => $asset->id]) }}" method="POST">
                        @csrf
                        
                        <!-- Asset Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ _lang('Asset Name') }}</label>
                                    <input type="text" class="form-control" value="{{ $asset->name }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ _lang('Asset Code') }}</label>
                                    <input type="text" class="form-control" value="{{ $asset->asset_code }}" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ _lang('Purchase Value') }}</label>
                                    <input type="text" class="form-control" value="{{ number_format($asset->purchase_value, 2) }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ _lang('Current Value') }}</label>
                                    <input type="text" class="form-control" value="{{ number_format($asset->calculateCurrentValue(), 2) }}" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Sale Details -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ _lang('Sale Details') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sale_price">{{ _lang('Sale Price') }} <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control @error('sale_price') is-invalid @enderror" 
                                                   id="sale_price" name="sale_price" value="{{ old('sale_price') }}" required>
                                            @error('sale_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sale_date">{{ _lang('Sale Date') }} <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('sale_date') is-invalid @enderror" 
                                                   id="sale_date" name="sale_date" value="{{ old('sale_date', date('Y-m-d')) }}" required>
                                            @error('sale_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="buyer_name">{{ _lang('Buyer Name') }} <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('buyer_name') is-invalid @enderror" 
                                                   id="buyer_name" name="buyer_name" value="{{ old('buyer_name') }}" required>
                                            @error('buyer_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="payment_method_id">{{ _lang('Payment Method') }} <span class="text-danger">*</span></label>
                                            <select class="form-control @error('payment_method_id') is-invalid @enderror" 
                                                    id="payment_method_id" name="payment_method_id" required>
                                                <option value="">{{ _lang('Select Payment Method') }}</option>
                                                @foreach($paymentMethods as $paymentMethod)
                                                    <option value="{{ $paymentMethod->id }}" {{ old('payment_method_id') == $paymentMethod->id ? 'selected' : '' }}>
                                                        {{ $paymentMethod->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('payment_method_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group" id="bank_account_group" style="display: none;">
                                            <label for="bank_account_id">{{ _lang('Bank Account') }} <span class="text-danger">*</span></label>
                                            <select class="form-control @error('bank_account_id') is-invalid @enderror" 
                                                    id="bank_account_id" name="bank_account_id">
                                                <option value="">{{ _lang('Select Bank Account') }}</option>
                                                @foreach($bankAccounts as $bankAccount)
                                                    <option value="{{ $bankAccount->id }}" {{ old('bank_account_id') == $bankAccount->id ? 'selected' : '' }}>
                                                        {{ $bankAccount->bank_name }} - {{ $bankAccount->account_name }} 
                                                        ({{ $bankAccount->account_number }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('bank_account_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="sale_reason">{{ _lang('Sale Reason') }}</label>
                                            <textarea class="form-control @error('sale_reason') is-invalid @enderror" 
                                                      id="sale_reason" name="sale_reason" rows="3">{{ old('sale_reason') }}</textarea>
                                            @error('sale_reason')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-money-bill-wave me-1"></i> {{ _lang('Sell Asset') }}
                            </button>
                            <a href="{{ route('assets.index', ['tenant' => app('tenant')->slug]) }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> {{ _lang('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Summary') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ _lang('Asset Information') }}</h6>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Name') }}:</strong> {{ $asset->name }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Code') }}:</strong> {{ $asset->asset_code }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Category') }}:</strong> {{ $asset->category->name }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Status') }}:</strong> {{ ucfirst($asset->status) }}</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-success">{{ _lang('Financial Information') }}</h6>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Purchase Value') }}:</strong> {{ number_format($asset->purchase_value, 2) }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Current Value') }}:</strong> {{ number_format($asset->calculateCurrentValue(), 2) }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Purchase Date') }}:</strong> {{ $asset->purchase_date }}</p>
                    </div>

                    @if($asset->is_leasable)
                    <div class="mb-0">
                        <h6 class="text-info">{{ _lang('Lease Information') }}</h6>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Leasable') }}:</strong> {{ _lang('Yes') }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Lease Rate') }}:</strong> {{ number_format($asset->lease_rate, 2) }} {{ _lang('per') }} {{ $asset->lease_rate_type }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Sale Guidelines') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-warning">{{ _lang('Important Notes') }}</h6>
                        <ul class="text-muted small">
                            <li>{{ _lang('Ensure the sale price is reasonable and reflects the asset\'s current condition') }}</li>
                            <li>{{ _lang('All sales will create corresponding financial transactions') }}</li>
                            <li>{{ _lang('The asset status will be changed to "Sold" after the sale') }}</li>
                            <li>{{ _lang('Sale information will be recorded for audit purposes') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#payment_method_id').change(function() {
            var selectedPaymentMethod = $(this).find('option:selected').text().toLowerCase();
            
            // Show bank account selection for bank-related payment methods
            if (selectedPaymentMethod.includes('bank') || selectedPaymentMethod.includes('transfer')) {
                $('#bank_account_group').show();
                $('#bank_account_id').prop('required', true);
            } else {
                $('#bank_account_group').hide();
                $('#bank_account_id').prop('required', false);
            }
        });

        // Set default sale price to current value
        $('#sale_price').val('{{ $asset->calculateCurrentValue() }}');
    });
</script>
@endpush
