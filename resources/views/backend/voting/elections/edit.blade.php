@extends('layouts.app')

@section('title', _lang('Edit Election'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ _lang('Edit Election') }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('voting.elections.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Elections') }}
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('voting.elections.update', $election->id) }}">
                        @csrf
                        @method('PUT')
                        
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
                                                   id="title" name="title" value="{{ old('title', $election->title) }}" required>
                                            @error('title')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="description">{{ _lang('Description') }}</label>
                                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                                      id="description" name="description" rows="3">{{ old('description', $election->description) }}</textarea>
                                            @error('description')
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
                                                           value="{{ old('start_date', $election->start_date->format('Y-m-d\TH:i')) }}" required>
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
                                                           value="{{ old('end_date', $election->end_date->format('Y-m-d\TH:i')) }}" required>
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
                                                   name="allow_abstain" value="1" {{ old('allow_abstain', $election->allow_abstain) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="allow_abstain">
                                                {{ _lang('Allow Abstain') }}
                                            </label>
                                            <small class="form-text text-muted">
                                                {{ _lang('Members can choose to abstain from voting') }}
                                            </small>
                                        </div>

                                        <div class="form-check mt-3">
                                            <input class="form-check-input" type="checkbox" id="weighted_voting" 
                                                   name="weighted_voting" value="1" {{ old('weighted_voting', $election->weighted_voting) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="weighted_voting">
                                                {{ _lang('Weighted Voting') }}
                                            </label>
                                            <small class="form-text text-muted">
                                                {{ _lang('Votes can be weighted based on member criteria') }}
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Election Info (Read-only) -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h4>{{ _lang('Election Information') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>{{ _lang('Type:') }}</strong> {{ ucfirst(str_replace('_', ' ', $election->type)) }}</p>
                                        <p><strong>{{ _lang('Voting Mechanism:') }}</strong> {{ ucfirst(str_replace('_', ' ', $election->voting_mechanism)) }}</p>
                                        <p><strong>{{ _lang('Privacy Mode:') }}</strong> {{ ucfirst($election->privacy_mode) }}</p>
                                        <p><strong>{{ _lang('Status:') }}</strong> 
                                            <span class="badge badge-{{ $election->status === 'draft' ? 'secondary' : ($election->status === 'active' ? 'success' : 'dark') }}">
                                                {{ ucfirst($election->status) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Update Election') }}
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
    
    startDateInput.addEventListener('change', function() {
        const startDate = new Date(this.value);
        const minEndDate = new Date(startDate.getTime() + 60 * 60 * 1000); // Add 1 hour
        endDateInput.min = minEndDate.toISOString().slice(0, 16);
    });
});
</script>
@endsection
