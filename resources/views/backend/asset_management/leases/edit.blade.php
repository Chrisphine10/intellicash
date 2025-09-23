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
                        <li class="breadcrumb-item"><a href="{{ route('asset-leases.index') }}">{{ _lang('Leases') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Edit Lease') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Edit Lease') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Edit Lease') }} #{{ $lease->id }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('asset-leases.update', $lease) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asset_id">{{ _lang('Asset') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('asset_id') is-invalid @enderror" 
                                            id="asset_id" name="asset_id" required>
                                        <option value="">{{ _lang('Select Asset') }}</option>
                                        @foreach(\App\Models\Asset::where('tenant_id', $tenant->id)->where('is_leasable', true)->get() as $asset)
                                            <option value="{{ $asset->id }}" {{ old('asset_id', $lease->asset_id) == $asset->id ? 'selected' : '' }}>
                                                {{ $asset->name }} ({{ $asset->asset_code }})
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
                                        @foreach(\App\Models\Member::where('tenant_id', $tenant->id)->where('status', 'active')->get() as $member)
                                            <option value="{{ $member->id }}" {{ old('member_id', $lease->member_id) == $member->id ? 'selected' : '' }}>
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
                                           id="start_date" name="start_date" value="{{ old('start_date', $lease->start_date) }}" required>
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date">{{ _lang('End Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                           id="end_date" name="end_date" value="{{ old('end_date', $lease->end_date) }}" required>
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
                                           id="daily_rate" name="daily_rate" value="{{ old('daily_rate', $lease->daily_rate) }}" required>
                                    @error('daily_rate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="deposit">{{ _lang('Deposit') }}</label>
                                    <input type="number" step="0.01" class="form-control @error('deposit') is-invalid @enderror" 
                                           id="deposit" name="deposit" value="{{ old('deposit', $lease->deposit) }}">
                                    @error('deposit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">{{ _lang('Status') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('status') is-invalid @enderror" 
                                            id="status" name="status" required>
                                        <option value="active" {{ old('status', $lease->status) === 'active' ? 'selected' : '' }}>
                                            {{ _lang('Active') }}
                                        </option>
                                        <option value="completed" {{ old('status', $lease->status) === 'completed' ? 'selected' : '' }}>
                                            {{ _lang('Completed') }}
                                        </option>
                                        <option value="cancelled" {{ old('status', $lease->status) === 'cancelled' ? 'selected' : '' }}>
                                            {{ _lang('Cancelled') }}
                                        </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="total_amount">{{ _lang('Total Amount') }}</label>
                                    <input type="number" step="0.01" class="form-control @error('total_amount') is-invalid @enderror" 
                                           id="total_amount" name="total_amount" value="{{ old('total_amount', $lease->total_amount) }}" readonly>
                                    @error('total_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">{{ _lang('Notes') }}</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes', $lease->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ _lang('Update Lease') }}
                            </button>
                            <a href="{{ route('asset-leases.show', $lease) }}" class="btn btn-secondary">
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
                    <h4 class="card-title mb-0">{{ _lang('Lease Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-primary">{{ $lease->total_days }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Total Days') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success">{{ formatAmount($lease->total_amount) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Total Amount') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h5>{{ $lease->asset->name }}</h5>
                        <p class="text-muted">{{ $lease->asset->asset_code }}</p>
                        <span class="badge badge-secondary">{{ $lease->asset->category->name }}</span>
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
                $('#total_amount').val(totalAmount.toFixed(2));
            }
        }
        
        $('#start_date, #end_date, #daily_rate').on('change', calculateTotal);
    });
</script>
@endpush
