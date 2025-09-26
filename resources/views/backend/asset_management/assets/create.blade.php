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
                        <li class="breadcrumb-item active">{{ _lang('Create Asset') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Create New Asset') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Information') }}</h4>
                    <div class="card-tools">
                        <a href="{{ route('assets.index', ['tenant' => app('tenant')->slug]) }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Assets') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('assets.store', ['tenant' => app('tenant')->slug]) }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">{{ _lang('Asset Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asset_code">{{ _lang('Asset Code') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('asset_code') is-invalid @enderror" 
                                           id="asset_code" name="asset_code" value="{{ old('asset_code') }}" required>
                                    @error('asset_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category_id">{{ _lang('Category') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('category_id') is-invalid @enderror" 
                                            id="category_id" name="category_id" required>
                                        <option value="">{{ _lang('Select Category') }}</option>
                                        @foreach(\App\Models\AssetCategory::where('tenant_id', app('tenant')->id)->where('is_active', true)->get() as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }} ({{ ucfirst($category->type) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">{{ _lang('Status') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('status') is-invalid @enderror" 
                                            id="status" name="status" required>
                                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>
                                            {{ _lang('Active') }}
                                        </option>
                                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>
                                            {{ _lang('Inactive') }}
                                        </option>
                                        <option value="maintenance" {{ old('status') === 'maintenance' ? 'selected' : '' }}>
                                            {{ _lang('Under Maintenance') }}
                                        </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">{{ _lang('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Purchase Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ _lang('Purchase Information') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="purchase_value">{{ _lang('Purchase Value') }} <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control @error('purchase_value') is-invalid @enderror" 
                                                   id="purchase_value" name="purchase_value" value="{{ old('purchase_value') }}" required>
                                            @error('purchase_value')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="current_value">{{ _lang('Current Value') }}</label>
                                            <input type="number" step="0.01" class="form-control @error('current_value') is-invalid @enderror" 
                                                   id="current_value" name="current_value" value="{{ old('current_value') }}">
                                            @error('current_value')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
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
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="supplier_name">{{ _lang('Supplier/Vendor Name') }}</label>
                                            <input type="text" class="form-control @error('supplier_name') is-invalid @enderror" 
                                                   id="supplier_name" name="supplier_name" value="{{ old('supplier_name') }}">
                                            @error('supplier_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="invoice_number">{{ _lang('Invoice/Receipt Number') }}</label>
                                            <input type="text" class="form-control @error('invoice_number') is-invalid @enderror" 
                                                   id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}">
                                            @error('invoice_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="purchase_date">{{ _lang('Purchase Date') }}</label>
                                    <input type="date" class="form-control @error('purchase_date') is-invalid @enderror" 
                                           id="purchase_date" name="purchase_date" value="{{ old('purchase_date') }}">
                                    @error('purchase_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="warranty_expiry">{{ _lang('Warranty Expiry') }}</label>
                                    <input type="date" class="form-control @error('warranty_expiry') is-invalid @enderror" 
                                           id="warranty_expiry" name="warranty_expiry" value="{{ old('warranty_expiry') }}">
                                    @error('warranty_expiry')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="location">{{ _lang('Location') }}</label>
                            <input type="text" class="form-control @error('location') is-invalid @enderror" 
                                   id="location" name="location" value="{{ old('location') }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Depreciation Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ _lang('Depreciation Settings') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="depreciation_method">{{ _lang('Depreciation Method') }}</label>
                                            <select class="form-control @error('depreciation_method') is-invalid @enderror" 
                                                    id="depreciation_method" name="depreciation_method">
                                                <option value="">{{ _lang('Select Method') }}</option>
                                                <option value="straight_line" {{ old('depreciation_method') === 'straight_line' ? 'selected' : '' }}>
                                                    {{ _lang('Straight Line') }}
                                                </option>
                                                <option value="declining_balance" {{ old('depreciation_method') === 'declining_balance' ? 'selected' : '' }}>
                                                    {{ _lang('Declining Balance') }}
                                                </option>
                                            </select>
                                            @error('depreciation_method')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="useful_life">{{ _lang('Useful Life (Years)') }}</label>
                                            <input type="number" class="form-control @error('useful_life') is-invalid @enderror" 
                                                   id="useful_life" name="useful_life" value="{{ old('useful_life') }}">
                                            @error('useful_life')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="salvage_value">{{ _lang('Salvage Value') }}</label>
                                            <input type="number" step="0.01" class="form-control @error('salvage_value') is-invalid @enderror" 
                                                   id="salvage_value" name="salvage_value" value="{{ old('salvage_value') }}">
                                            @error('salvage_value')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Lease Settings -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ _lang('Lease Settings') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="is_leasable" name="is_leasable" 
                                               value="1" {{ old('is_leasable') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_leasable">
                                            {{ _lang('This asset can be leased to members') }}
                                        </label>
                                    </div>
                                </div>

                                <div id="lease_settings" style="{{ old('is_leasable') ? '' : 'display: none;' }}">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lease_rate">{{ _lang('Lease Rate') }}</label>
                                                <input type="number" step="0.01" class="form-control @error('lease_rate') is-invalid @enderror" 
                                                       id="lease_rate" name="lease_rate" value="{{ old('lease_rate') }}">
                                                @error('lease_rate')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lease_rate_type">{{ _lang('Rate Type') }}</label>
                                                <select class="form-control @error('lease_rate_type') is-invalid @enderror" 
                                                        id="lease_rate_type" name="lease_rate_type">
                                                    <option value="daily" {{ old('lease_rate_type') === 'daily' ? 'selected' : '' }}>
                                                        {{ _lang('Daily') }}
                                                    </option>
                                                    <option value="weekly" {{ old('lease_rate_type') === 'weekly' ? 'selected' : '' }}>
                                                        {{ _lang('Weekly') }}
                                                    </option>
                                                    <option value="monthly" {{ old('lease_rate_type') === 'monthly' ? 'selected' : '' }}>
                                                        {{ _lang('Monthly') }}
                                                    </option>
                                                </select>
                                                @error('lease_rate_type')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">{{ _lang('Notes') }}</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ _lang('Create Asset') }}
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
                    <h4 class="card-title mb-0">{{ _lang('Asset Types') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ _lang('Fixed Assets') }}</h6>
                        <p class="text-muted small">{{ _lang('Office equipment, buildings, machinery that are not leased out') }}</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-success">{{ _lang('Investment Assets') }}</h6>
                        <p class="text-muted small">{{ _lang('Government bonds, mutual funds, stocks, and other investments') }}</p>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-info">{{ _lang('Leasable Assets') }}</h6>
                        <p class="text-muted small">{{ _lang('Vehicles, equipment, tents that can be leased to members') }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Depreciation Methods') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ _lang('Straight Line') }}</h6>
                        <p class="text-muted small">{{ _lang('Equal depreciation each year over the useful life') }}</p>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-success">{{ _lang('Declining Balance') }}</h6>
                        <p class="text-muted small">{{ _lang('Higher depreciation in early years, decreasing over time') }}</p>
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
        $('#is_leasable').change(function() {
            if ($(this).is(':checked')) {
                $('#lease_settings').show();
            } else {
                $('#lease_settings').hide();
            }
        });

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

        // Auto-fill current value with purchase value
        $('#purchase_value').change(function() {
            if (!$('#current_value').val()) {
                $('#current_value').val($(this).val());
            }
        });
    });
</script>
@endpush