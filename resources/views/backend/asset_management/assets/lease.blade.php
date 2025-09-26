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
                        <li class="breadcrumb-item active">{{ _lang('Create Lease') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Create Lease for') }}: {{ $asset->name }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Lease Information') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('assets.create-lease', ['tenant' => app('tenant')->slug, 'asset' => $asset->id]) }}" method="POST">
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
                                    <label>{{ _lang('Lease Rate') }}</label>
                                    <input type="text" class="form-control" value="{{ number_format($asset->lease_rate, 2) }} {{ _lang('per') }} {{ $asset->lease_rate_type }}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{ _lang('Current Status') }}</label>
                                    <input type="text" class="form-control" value="{{ _lang('Available for Lease') }}" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Lease Details -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">{{ _lang('Lease Details') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="member_id">{{ _lang('Member') }} <span class="text-danger">*</span></label>
                                            <select class="form-control @error('member_id') is-invalid @enderror" 
                                                    id="member_id" name="member_id" required>
                                                <option value="">{{ _lang('Select Member') }}</option>
                                                @foreach($members as $member)
                                                    <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>
                                                        {{ $member->first_name }} {{ $member->last_name }} 
                                                        ({{ $member->member_no }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('member_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">{{ _lang('Start Date') }} <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control @error('start_date') is-invalid @enderror" 
                                                   id="start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                            @error('start_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="end_date">{{ _lang('End Date') }}</label>
                                            <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                                   id="end_date" name="end_date" value="{{ old('end_date') }}">
                                            <small class="form-text text-muted">{{ _lang('Leave empty for indefinite lease') }}</small>
                                            @error('end_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="deposit_amount">{{ _lang('Security Deposit') }}</label>
                                            <input type="number" step="0.01" class="form-control @error('deposit_amount') is-invalid @enderror" 
                                                   id="deposit_amount" name="deposit_amount" value="{{ old('deposit_amount') }}">
                                            @error('deposit_amount')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="notes">{{ _lang('Lease Notes') }}</label>
                                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                                      id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                                            @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-handshake me-1"></i> {{ _lang('Create Lease') }}
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
                        <h6 class="text-success">{{ _lang('Lease Information') }}</h6>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Lease Rate') }}:</strong> {{ number_format($asset->lease_rate, 2) }} {{ _lang('per') }} {{ $asset->lease_rate_type }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Available') }}:</strong> {{ _lang('Yes') }}</p>
                    </div>

                    <div class="mb-0">
                        <h6 class="text-info">{{ _lang('Financial Information') }}</h6>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Purchase Value') }}:</strong> {{ number_format($asset->purchase_value, 2) }}</p>
                        <p class="text-muted small mb-1"><strong>{{ _lang('Current Value') }}:</strong> {{ number_format($asset->calculateCurrentValue(), 2) }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Lease Guidelines') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-warning">{{ _lang('Important Notes') }}</h6>
                        <ul class="text-muted small">
                            <li>{{ _lang('Ensure the member is eligible for asset leasing') }}</li>
                            <li>{{ _lang('Set appropriate security deposit amount') }}</li>
                            <li>{{ _lang('Lease will be active immediately after creation') }}</li>
                            <li>{{ _lang('Member will be responsible for asset maintenance during lease') }}</li>
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
        // Set minimum end date to start date
        $('#start_date').change(function() {
            $('#end_date').attr('min', $(this).val());
        });
    });
</script>
@endpush
