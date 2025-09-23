@extends('layouts.app')

@section('title', _lang('Create Election'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ _lang('Create New Election') }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('voting.elections.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Elections') }}
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('voting.elections.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Basic Information -->
                                <div class="card">
                                    <div class="card-header">
                                        <h4>{{ _lang('Basic Information') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="title">{{ _lang('Election Title') }} <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                                   id="title" name="title" value="{{ old('title') }}" required>
                                            @error('title')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="description">{{ _lang('Description') }}</label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="position_id">{{ _lang('Position') }} <span id="position_required" class="text-danger" style="display: none;">*</span></label>
                                            <select class="form-control @error('position_id') is-invalid @enderror" 
                                                    id="position_id" name="position_id">
                                                <option value="">{{ _lang('Select Position') }}</option>
                                                @foreach($positions as $position)
                                                    <option value="{{ $position->id }}" 
                                                            data-max-winners="{{ $position->max_winners }}"
                                                            {{ old('position_id') == $position->id ? 'selected' : '' }}>
                                                        {{ $position->name }} ({{ _lang('Max Winners') }}: {{ $position->max_winners }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('position_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted" id="position_help">
                                                {{ _lang('Select a position for this election') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Election Configuration -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4>{{ _lang('Election Configuration') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="type">{{ _lang('Election Type') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control @error('type') is-invalid @enderror" 
                                                            id="type" name="type" required>
                                                        <option value="">{{ _lang('Select Type') }}</option>
                                                        <option value="single_winner" {{ old('type') == 'single_winner' ? 'selected' : '' }}>
                                                            {{ _lang('Single Winner') }}
                                                        </option>
                                                        <option value="multi_position" {{ old('type') == 'multi_position' ? 'selected' : '' }}>
                                                            {{ _lang('Multi Position') }}
                                                        </option>
                                                        <option value="referendum" {{ old('type') == 'referendum' ? 'selected' : '' }}>
                                                            {{ _lang('Referendum') }}
                                                        </option>
                                                    </select>
                                                    @error('type')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="voting_mechanism">{{ _lang('Voting Mechanism') }} <span class="text-danger">*</span></label>
                                                    <select class="form-control @error('voting_mechanism') is-invalid @enderror" 
                                                            id="voting_mechanism" name="voting_mechanism" required>
                                                        <option value="">{{ _lang('Select Mechanism') }}</option>
                                                        <option value="majority" {{ old('voting_mechanism') == 'majority' ? 'selected' : '' }}>
                                                            {{ _lang('Majority Vote') }}
                                                        </option>
                                                        <option value="ranked_choice" {{ old('voting_mechanism') == 'ranked_choice' ? 'selected' : '' }}>
                                                            {{ _lang('Ranked Choice') }}
                                                        </option>
                                                        <option value="weighted" {{ old('voting_mechanism') == 'weighted' ? 'selected' : '' }}>
                                                            {{ _lang('Weighted Vote') }}
                                                        </option>
                                                    </select>
                                                    @error('voting_mechanism')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="privacy_mode">{{ _lang('Privacy Mode') }} <span class="text-danger">*</span></label>
                                            <select class="form-control @error('privacy_mode') is-invalid @enderror" 
                                                    id="privacy_mode" name="privacy_mode" required>
                                                <option value="">{{ _lang('Select Privacy Mode') }}</option>
                                                <option value="private" {{ old('privacy_mode') == 'private' ? 'selected' : '' }}>
                                                    {{ _lang('Private - Only results visible') }}
                                                </option>
                                                <option value="public" {{ old('privacy_mode') == 'public' ? 'selected' : '' }}>
                                                    {{ _lang('Public - All votes visible') }}
                                                </option>
                                                <option value="hybrid" {{ old('privacy_mode') == 'hybrid' ? 'selected' : '' }}>
                                                    {{ _lang('Hybrid - Admins see all, members see totals') }}
                                                </option>
                                            </select>
                                            @error('privacy_mode')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Voting Period -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4>{{ _lang('Voting Period') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="start_date">{{ _lang('Start Date & Time') }} <span class="text-danger">*</span></label>
                                                    <input type="datetime-local" class="form-control @error('start_date') is-invalid @enderror" 
                                                           id="start_date" name="start_date" 
                                                           value="{{ old('start_date', now()->addHour()->format('Y-m-d\TH:i')) }}" required>
                                                    @error('start_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="end_date">{{ _lang('End Date & Time') }} <span class="text-danger">*</span></label>
                                                    <input type="datetime-local" class="form-control @error('end_date') is-invalid @enderror" 
                                                           id="end_date" name="end_date" 
                                                           value="{{ old('end_date', now()->addDays(7)->format('Y-m-d\TH:i')) }}" required>
                                                    @error('end_date')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <!-- Voting Options -->
                                <div class="card">
                                    <div class="card-header">
                                        <h4>{{ _lang('Voting Options') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allow_abstain" 
                                                   name="allow_abstain" value="1" {{ old('allow_abstain') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="allow_abstain">
                                                {{ _lang('Allow Abstain') }}
                                            </label>
                                            <small class="form-text text-muted">
                                                {{ _lang('Members can choose to abstain from voting') }}
                                            </small>
                                        </div>

                                        <div class="form-check mt-3">
                                            <input class="form-check-input" type="checkbox" id="weighted_voting" 
                                                   name="weighted_voting" value="1" {{ old('weighted_voting') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="weighted_voting">
                                                {{ _lang('Weighted Voting') }}
                                            </label>
                                            <small class="form-text text-muted">
                                                {{ _lang('Votes can be weighted based on member criteria') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Help -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4>{{ _lang('Help') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <h6>{{ _lang('Election Types:') }}</h6>
                                        <ul class="small">
                                            <li><strong>{{ _lang('Single Winner:') }}</strong> {{ _lang('Elect one person for a position') }}</li>
                                            <li><strong>{{ _lang('Multi Position:') }}</strong> {{ _lang('Elect multiple people for committee roles. Requires position with max_winners > 1') }}</li>
                                            <li><strong>{{ _lang('Referendum:') }}</strong> {{ _lang('Yes/No vote on a proposal') }}</li>
                                        </ul>

                                        <h6 class="mt-3">{{ _lang('Voting Mechanisms:') }}</h6>
                                        <ul class="small">
                                            <li><strong>{{ _lang('Majority:') }}</strong> {{ _lang('Most votes wins') }}</li>
                                            <li><strong>{{ _lang('Ranked Choice:') }}</strong> {{ _lang('Members rank preferences') }}</li>
                                            <li><strong>{{ _lang('Weighted:') }}</strong> {{ _lang('Votes weighted by criteria') }}</li>
                                        </ul>

                                        <h6 class="mt-3">{{ _lang('Privacy Modes:') }}</h6>
                                        <ul class="small">
                                            <li><strong>{{ _lang('Private:') }}</strong> {{ _lang('Only aggregated results visible') }}</li>
                                            <li><strong>{{ _lang('Public:') }}</strong> {{ _lang('All individual votes visible') }}</li>
                                            <li><strong>{{ _lang('Hybrid:') }}</strong> {{ _lang('Admins see all, members see totals') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Create Election') }}
                                </button>
                                <a href="{{ route('voting.elections.index') }}" class="btn btn-secondary">
                                    {{ _lang('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const typeSelect = document.getElementById('type');
    const positionSelect = document.getElementById('position_id');
    const positionRequired = document.getElementById('position_required');
    const positionHelp = document.getElementById('position_help');
    const privacyModeSelect = document.getElementById('privacy_mode');
    
    // Handle date validation
    startDateInput.addEventListener('change', function() {
        const startDate = new Date(this.value);
        const minEndDate = new Date(startDate.getTime() + 60 * 60 * 1000); // Add 1 hour
        endDateInput.min = minEndDate.toISOString().slice(0, 16);
    });
    
    // Handle election type changes
    typeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        const selectedPosition = positionSelect.options[positionSelect.selectedIndex];
        
        if (selectedType === 'multi_position') {
            // Make position required for Multi Position elections
            positionRequired.style.display = 'inline';
            positionSelect.required = true;
            positionHelp.textContent = '{{ _lang("Multi Position elections require a position with max_winners > 1") }}';
            
            // Validate selected position
            if (selectedPosition.value && parseInt(selectedPosition.dataset.maxWinners) <= 1) {
                positionHelp.innerHTML = '<span class="text-danger">{{ _lang("Selected position must have max_winners > 1 for Multi Position elections") }}</span>';
            }
        } else {
            // Make position optional for other types
            positionRequired.style.display = 'none';
            positionSelect.required = false;
            positionHelp.textContent = '{{ _lang("Select a position for this election") }}';
        }
    });
    
    // Handle position selection changes
    positionSelect.addEventListener('change', function() {
        const selectedPosition = this.options[this.selectedIndex];
        const selectedType = typeSelect.value;
        
        if (selectedType === 'multi_position' && selectedPosition.value) {
            const maxWinners = parseInt(selectedPosition.dataset.maxWinners);
            if (maxWinners <= 1) {
                positionHelp.innerHTML = '<span class="text-danger">{{ _lang("This position has max_winners = 1. Multi Position elections require max_winners > 1") }}</span>';
            } else {
                positionHelp.innerHTML = '<span class="text-success">{{ _lang("This position supports Multi Position elections") }} ({{ _lang("Max Winners") }}: ' + maxWinners + ')</span>';
            }
        }
    });
    
    // Handle privacy mode changes
    privacyModeSelect.addEventListener('change', function() {
        const selectedMode = this.value;
        const helpTexts = {
            'private': '{{ _lang("Only aggregated results will be visible to members") }}',
            'public': '{{ _lang("All individual votes will be visible to everyone") }}',
            'hybrid': '{{ _lang("Admins see all votes, members see only aggregated results") }}'
        };
        
        // Update help text if needed
        if (helpTexts[selectedMode]) {
            // You can add help text display here if needed
        }
    });
    
    // Initialize on page load
    if (typeSelect.value) {
        typeSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endsection
