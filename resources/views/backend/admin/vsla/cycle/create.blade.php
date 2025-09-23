@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('vsla.cycles.index') }}">{{ _lang('VSLA Cycles') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ _lang('Create New Cycle') }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Create New VSLA Cycle') }}</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('vsla.cycles.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="cycle_name">{{ _lang('Cycle Name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('cycle_name') is-invalid @enderror" 
                                       id="cycle_name" name="cycle_name" value="{{ old('cycle_name') }}" 
                                       placeholder="{{ _lang('Enter cycle name (e.g., Cycle 2024-1)') }}" required>
                                @error('cycle_name')
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
                                       id="start_date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">{{ _lang('End Date') }} <small class="text-muted">({{ _lang('Optional - can be set later') }})</small></label>
                                <input type="date" class="form-control @error('end_date') is-invalid @enderror" 
                                       id="end_date" name="end_date" value="{{ old('end_date') }}">
                                @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">{{ _lang('Leave empty for open-ended cycle') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="notes">{{ _lang('Notes') }}</label>
                                <textarea class="form-control @error('notes') is-invalid @enderror" 
                                          id="notes" name="notes" rows="3" 
                                          placeholder="{{ _lang('Optional notes about this cycle...') }}">{{ old('notes') }}</textarea>
                                @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> {{ _lang('Important Notes') }}</h6>
                        <ul class="mb-0">
                            <li>{{ _lang('A new cycle will be created with active status') }}</li>
                            <li>{{ _lang('Members can start making transactions once the cycle is active') }}</li>
                            <li>{{ _lang('You can update cycle totals and end the cycle from the cycle management page') }}</li>
                            <li>{{ _lang('Share-out process can only begin after the cycle is ended') }}</li>
                        </ul>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> {{ _lang('Create Cycle') }}
                        </button>
                        <a href="{{ route('vsla.cycles.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Cancel') }}
                        </a>
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
    // Auto-generate cycle name based on current date
    $('#start_date').on('change', function() {
        var startDate = new Date($(this).val());
        var year = startDate.getFullYear();
        var month = startDate.getMonth() + 1;
        
        if ($('#cycle_name').val() === '') {
            $('#cycle_name').val('Cycle ' + year + '-' + month);
        }
    });
    
    // Validate end date is after start date
    $('#end_date').on('change', function() {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($(this).val());
        
        if (endDate <= startDate) {
            toastr.error('{{ _lang("End date must be after start date") }}');
            $(this).val('');
        }
    });
});
</script>
@endsection
