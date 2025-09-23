@extends('layouts.app')

@section('title', _lang('Vote in Election'))

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ _lang('Vote in Election') }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('voting.elections.show', $election->id) }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Election') }}
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Election Info -->
                    <div class="alert alert-info">
                        <h5><strong>{{ $election->title }}</strong></h5>
                        @if($election->description)
                            <p class="mb-2">{{ $election->description }}</p>
                        @endif
                        <p class="mb-0">
                            <strong>{{ _lang('Voting Period:') }}</strong> 
                            {{ $election->start_date->format('M d, Y H:i') }} - {{ $election->end_date->format('M d, Y H:i') }}
                        </p>
                    </div>

                    @if($existingVote)
                        <!-- Already Voted Message -->
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> {{ _lang('You Have Already Voted') }}</h5>
                            <p class="mb-2">{{ _lang('Thank you for participating in this election. Your vote has been securely recorded.') }}</p>
                            <p class="mb-2">
                                <strong>{{ _lang('Voted on:') }}</strong> 
                                {{ $existingVote->voted_at->format('M d, Y H:i:s') }}
                            </p>
                            @if($election->type === 'referendum')
                                <p class="mb-0">
                                    <strong>{{ _lang('Your choice:') }}</strong> 
                                    <span class="badge badge-primary">{{ strtoupper($existingVote->choice) }}</span>
                                </p>
                            @elseif($existingVote->candidate_id)
                                <p class="mb-0">
                                    <strong>{{ _lang('Your choice:') }}</strong> 
                                    <span class="badge badge-primary">{{ $existingVote->candidate->name ?? 'Candidate' }}</span>
                                </p>
                            @else
                                <p class="mb-0">
                                    <strong>{{ _lang('Your choice:') }}</strong> 
                                    <span class="badge badge-secondary">{{ _lang('ABSTAIN') }}</span>
                                </p>
                            @endif
                        </div>

                        <!-- Vote Summary Card -->
                        <div class="card">
                            <div class="card-header">
                                <h6>{{ _lang('Your Vote Summary') }}</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>{{ _lang('Vote ID:') }}</strong> #{{ $existingVote->id }}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>{{ _lang('Security Score:') }}</strong> 
                                        @if($existingVote->security_score)
                                            <span class="badge badge-success">{{ $existingVote->security_score }}%</span>
                                        @else
                                            <span class="badge badge-info">{{ _lang('N/A') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong>{{ _lang('IP Address:') }}</strong> {{ $existingVote->ip_address ?? _lang('N/A') }}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>{{ _lang('Device:') }}</strong> {{ $existingVote->user_agent ? substr($existingVote->user_agent, 0, 50) . '...' : _lang('N/A') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Voting Instructions (Already Voted) -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6>{{ _lang('What Happens Next?') }}</h6>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>{{ _lang('Your vote has been securely recorded and cannot be changed') }}</li>
                                    <li>{{ _lang('Results will be available after the election closes') }}</li>
                                    <li>{{ _lang('You will be notified when results are published') }}</li>
                                    @if($election->privacy_mode === 'private')
                                        <li>{{ _lang('Your individual choice will remain private') }}</li>
                                    @elseif($election->privacy_mode === 'public')
                                        <li>{{ _lang('Your choice will be visible to other members') }}</li>
                                    @else
                                        <li>{{ _lang('Admins can see individual votes, members see only totals') }}</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    @else

                    <form method="POST" action="{{ route('voting.vote.submit', $election->id) }}" id="voting-form">
                        @csrf
                        
                        @if($election->type === 'referendum')
                            <!-- Referendum Voting -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Referendum Vote') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="font-weight-bold">{{ _lang('Please select your choice:') }}</label>
                                        <div class="mt-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="choice" id="choice_yes" value="yes" required>
                                                <label class="form-check-label" for="choice_yes">
                                                    <span class="badge badge-success badge-lg p-2">
                                                        <i class="fas fa-check-circle"></i> {{ _lang('YES') }}
                                                    </span>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="choice" id="choice_no" value="no" required>
                                                <label class="form-check-label" for="choice_no">
                                                    <span class="badge badge-danger badge-lg p-2">
                                                        <i class="fas fa-times-circle"></i> {{ _lang('NO') }}
                                                    </span>
                                                </label>
                                            </div>
                                            @if($election->allow_abstain)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="choice" id="choice_abstain" value="abstain" required>
                                                <label class="form-check-label" for="choice_abstain">
                                                    <span class="badge badge-secondary badge-lg p-2">
                                                        <i class="fas fa-minus-circle"></i> {{ _lang('ABSTAIN') }}
                                                    </span>
                                                </label>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <!-- Candidate Voting -->
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Select Candidate') }}</h5>
                                </div>
                                <div class="card-body">
                                    @if($election->voting_mechanism === 'ranked_choice')
                                        <div class="alert alert-warning">
                                            <i class="fas fa-info-circle"></i>
                                            {{ _lang('This is a ranked choice election. Please rank the candidates in order of preference (1 = first choice, 2 = second choice, etc.)') }}
                                        </div>
                                    @endif

                                    <div class="row">
                                        @foreach($election->candidates as $candidate)
                                        <div class="col-md-6 mb-3">
                                            <div class="card candidate-card" data-candidate-id="{{ $candidate->id }}">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        @if($election->voting_mechanism === 'ranked_choice')
                                                            <div class="d-flex align-items-center">
                                                                <div class="mr-3">
                                                                    <label class="form-check-label font-weight-bold">
                                                                        {{ _lang('Rank:') }}
                                                                    </label>
                                                                    <select name="rankings[{{ $candidate->id }}]" class="form-control form-control-sm ranking-select" style="width: 60px;">
                                                                        <option value="">{{ _lang('--') }}</option>
                                                                        @for($i = 1; $i <= $election->candidates->count(); $i++)
                                                                            <option value="{{ $i }}">{{ $i }}</option>
                                                                        @endfor
                                                                    </select>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <input class="form-check-input" type="radio" name="candidate_id" 
                                                                           id="candidate_{{ $candidate->id }}" value="{{ $candidate->id }}">
                                                                    <label class="form-check-label" for="candidate_{{ $candidate->id }}">
                                                                        <strong>{{ $candidate->name }}</strong>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <input class="form-check-input" type="radio" name="candidate_id" 
                                                                   id="candidate_{{ $candidate->id }}" value="{{ $candidate->id }}">
                                                            <label class="form-check-label" for="candidate_{{ $candidate->id }}">
                                                                <strong>{{ $candidate->name }}</strong>
                                                            </label>
                                                        @endif
                                                    </div>
                                                    
                                                    <div class="mt-2">
                                                        <small class="text-muted">
                                                            {{ $candidate->member->first_name }} {{ $candidate->member->last_name }}
                                                        </small>
                                                    </div>
                                                    
                                                    @if($candidate->bio)
                                                        <p class="mt-2 small">{{ $candidate->bio }}</p>
                                                    @endif
                                                    
                                                    @if($candidate->manifesto)
                                                        <div class="mt-2">
                                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                                    data-toggle="modal" data-target="#manifestoModal{{ $candidate->id }}">
                                                                <i class="fas fa-file-alt"></i> {{ _lang('View Manifesto') }}
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>

                                    @if($election->allow_abstain)
                                    <div class="mt-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="candidate_id" id="abstain" value="">
                                            <label class="form-check-label" for="abstain">
                                                <span class="badge badge-secondary badge-lg p-2">
                                                    <i class="fas fa-minus-circle"></i> {{ _lang('ABSTAIN') }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Voting Instructions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6>{{ _lang('Important Instructions') }}</h6>
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>{{ _lang('You can only vote once in this election') }}</li>
                                    <li>{{ _lang('Your vote cannot be changed once submitted') }}</li>
                                    @if($election->privacy_mode === 'private')
                                        <li>{{ _lang('Your individual choice will remain private') }}</li>
                                    @elseif($election->privacy_mode === 'public')
                                        <li>{{ _lang('Your choice will be visible to other members') }}</li>
                                    @else
                                        <li>{{ _lang('Admins can see individual votes, members see only totals') }}</li>
                                    @endif
                                    @if($election->weighted_voting)
                                        <li>{{ _lang('Your vote weight is: :weight', ['weight' => '1.0']) }}</li>
                                    @endif
                                </ul>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="submit-vote">
                                <i class="fas fa-vote-yea"></i> {{ _lang('Submit Vote') }}
                            </button>
                        </div>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manifesto Modals -->
@foreach($election->candidates as $candidate)
    @if($candidate->manifesto)
    <div class="modal fade" id="manifestoModal{{ $candidate->id }}" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ _lang('Manifesto - :name', ['name' => $candidate->name]) }}</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="white-space-pre-line">{{ $candidate->manifesto }}</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('voting-form');
    const submitBtn = document.getElementById('submit-vote');
    
    // Only run voting form logic if the form exists (user hasn't voted yet)
    if (!form || !submitBtn) {
        return;
    }
    
    // Validation for ranked choice voting
    if (document.querySelector('.ranking-select')) {
        const rankingSelects = document.querySelectorAll('.ranking-select');
        const candidateRadios = document.querySelectorAll('input[name="candidate_id"]');
        
        // Handle ranking selection
        rankingSelects.forEach(select => {
            select.addEventListener('change', function() {
                const selectedRank = this.value;
                const candidateId = this.name.match(/\[(\d+)\]/)[1];
                
                // Clear same rank from other candidates
                rankingSelects.forEach(otherSelect => {
                    if (otherSelect !== this && otherSelect.value === selectedRank) {
                        otherSelect.value = '';
                    }
                });
                
                // Auto-select the candidate radio if ranked
                if (selectedRank) {
                    const radio = document.getElementById('candidate_' + candidateId);
                    if (radio) {
                        radio.checked = true;
                    }
                }
            });
        });
        
        // Handle candidate selection
        candidateRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    const candidateId = this.value;
                    const rankingSelect = document.querySelector(`select[name="rankings[${candidateId}]"]`);
                    if (rankingSelect && !rankingSelect.value) {
                        // Auto-assign rank 1 if not already ranked
                        rankingSelect.value = '1';
                    }
                }
            });
        });
    }
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const choiceInputs = document.querySelectorAll('input[name="choice"]');
        const candidateInputs = document.querySelectorAll('input[name="candidate_id"]');
        
        let isValid = false;
        
        if (choiceInputs.length > 0) {
            // Referendum voting
            isValid = Array.from(choiceInputs).some(input => input.checked);
        } else if (candidateInputs.length > 0) {
            // Candidate voting
            isValid = Array.from(candidateInputs).some(input => input.checked);
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('{{ _lang("Please make a selection before submitting your vote.") }}');
            return;
        }
        
        // Confirm submission
        if (!confirm('{{ _lang("Are you sure you want to submit your vote? This action cannot be undone.") }}')) {
            e.preventDefault();
            return;
        }
        
        // Disable submit button to prevent double submission
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ _lang("Submitting...") }}';
    });
});
</script>

<style>
.candidate-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.candidate-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.candidate-card input[type="radio"]:checked + label {
    color: #007bff;
    font-weight: bold;
}

.ranking-select {
    display: inline-block;
}

.white-space-pre-line {
    white-space: pre-line;
}
</style>
@endsection
