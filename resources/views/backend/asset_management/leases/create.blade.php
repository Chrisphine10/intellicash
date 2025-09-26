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
                        <li class="breadcrumb-item"><a href="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}">{{ _lang('Leases') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Create Lease') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Create New Lease') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Lease Information') }}</h4>
                    <div class="card-tools">
                        <a href="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Leases') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('asset-leases.store', ['tenant' => app('tenant')->slug]) }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asset_id">{{ _lang('Asset') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('asset_id') is-invalid @enderror" 
                                            id="asset_id" name="asset_id" required>
                                        <option value="">{{ _lang('Select Asset') }}</option>
                                        @foreach($assets as $asset)
                                            <option value="{{ $asset->id }}" {{ old('asset_id') == $asset->id ? 'selected' : '' }}>
                                                {{ $asset->name }} ({{ $asset->asset_code }}) - {{ formatAmount($asset->lease_rate) }}/{{ ucfirst($asset->lease_rate_type) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('asset_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="member_id">{{ _lang('Member') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('member_id') is-invalid @enderror" 
                                            id="member_id" name="member_id" required>
                                        <option value="">{{ _lang('Select Member') }}</option>
                                        @foreach($members as $member)
                                            <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>
                                                {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('member_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date">{{ _lang('Start Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                           id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">{{ _lang('End Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                           id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="daily_rate">{{ _lang('Daily Rate') }} <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control @error('daily_rate') is-invalid @enderror" 
                                           id="daily_rate" name="daily_rate" value="{{ old('daily_rate') }}" required>
                                    @error('daily_rate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="deposit">{{ _lang('Deposit') }}</label>
                                    <input type="number" step="0.01" class="form-control @error('deposit') is-invalid @enderror" 
                                           id="deposit" name="deposit" value="{{ old('deposit') }}">
                                    @error('deposit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="total_days">{{ _lang('Total Days') }}</label>
                                    <input type="number" class="form-control @error('total_days') is-invalid @enderror" 
                                           id="total_days" name="total_days" value="{{ old('total_days') }}" readonly>
                                    @error('total_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="total_amount">{{ _lang('Total Amount') }}</label>
                                    <input type="number" step="0.01" class="form-control @error('total_amount') is-invalid @enderror" 
                                           id="total_amount" name="total_amount" value="{{ old('total_amount') }}" readonly>
                                    @error('total_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
                                <i class="fas fa-save me-1"></i> {{ _lang('Create Lease') }}
                            </button>
                            <a href="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}" class="btn btn-secondary">
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
                    <h4 class="card-title mb-0">{{ _lang('Available Assets') }}</h4>
                </div>
                <div class="card-body">
                    @php
                        $availableAssets = $assets;
                    @endphp
                    
                    @if($availableAssets->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($availableAssets as $asset)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $asset->name }}</h6>
                                        <p class="mb-1 text-muted">{{ $asset->asset_code }}</p>
                                        <small class="text-success">
                                            {{ formatAmount($asset->lease_rate) }}/{{ ucfirst($asset->lease_rate_type) }}
                                        </small>
                                    </div>
                                    <span class="badge badge-success">{{ _lang('Available') }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-building fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">{{ _lang('No available assets') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Lease Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ _lang('Lease Process') }}</h6>
                        <ol class="text-muted small">
                            <li>{{ _lang('Select an available asset') }}</li>
                            <li>{{ _lang('Choose a member to lease to') }}</li>
                            <li>{{ _lang('Set start and end dates') }}</li>
                            <li>{{ _lang('Set daily rate and deposit') }}</li>
                            <li>{{ _lang('Review and create lease') }}</li>
                        </ol>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-success">{{ _lang('Payment Terms') }}</h6>
                        <p class="text-muted small">{{ _lang('Deposits are collected upfront. Daily rates are calculated based on the lease duration.') }}</p>
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
        // Calculate total amount when dates or daily rate change
        function calculateTotal() {
            const startDate = new Date($('#start_date').val());
            const endDate = new Date($('#end_date').val());
            const dailyRate = parseFloat($('#daily_rate').val()) || 0;
            
            if (startDate && endDate && dailyRate) {
                const timeDiff = endDate.getTime() - startDate.getTime();
                const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
                const totalAmount = daysDiff * dailyRate;
                $('#total_days').val(daysDiff);
                $('#total_amount').val(totalAmount.toFixed(2));
            }
        }
        
        // Auto-fill daily rate when asset is selected
        $('#asset_id').on('change', function() {
            const assetId = $(this).val();
            if (assetId) {
                // You can make an AJAX call here to get the asset's lease rate
                // For now, we'll just enable the daily rate field
                $('#daily_rate').prop('disabled', false);
            }
        });
        
        $('#start_date, #end_date, #daily_rate').on('change', calculateTotal);
    });
</script>
@endpush
