@extends('layouts.app')

@section('title', _lang('Manage Candidates'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h3 class="card-title">{{ _lang('Manage Candidates') }}</h3>
                            <p class="text-muted mb-0">{{ $election->title }}</p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="{{ route('voting.elections.show', $election->id) }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> {{ _lang('Back to Election') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Add Candidate Form -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>{{ _lang('Add New Candidate') }}</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('voting.candidates.add', $election->id) }}">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="member_id">{{ _lang('Select Member') }} <span class="text-danger">*</span></label>
                                            <select class="form-control @error('member_id') is-invalid @enderror" 
                                                    id="member_id" name="member_id" required>
                                                <option value="">{{ _lang('Choose Member') }}</option>
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
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="name">{{ _lang('Candidate Name') }} <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" name="name" value="{{ old('name') }}" required>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="bio">{{ _lang('Bio') }}</label>
                                            <textarea class="form-control @error('bio') is-invalid @enderror" 
                                                      id="bio" name="bio" rows="2">{{ old('bio') }}</textarea>
                                            @error('bio')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="manifesto">{{ _lang('Manifesto') }}</label>
                                            <textarea class="form-control @error('manifesto') is-invalid @enderror" 
                                                      id="manifesto" name="manifesto" rows="3">{{ old('manifesto') }}</textarea>
                                            @error('manifesto')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> {{ _lang('Add Candidate') }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Current Candidates -->
                    <div class="card">
                        <div class="card-header">
                            <h5>{{ _lang('Current Candidates') }} ({{ $election->candidates->count() }})</h5>
                        </div>
                        <div class="card-body">
                            @if($election->candidates->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>{{ _lang('Name') }}</th>
                                                <th>{{ _lang('Member') }}</th>
                                                <th>{{ _lang('Bio') }}</th>
                                                <th>{{ _lang('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($election->candidates as $candidate)
                                            <tr>
                                                <td>
                                                    <strong>{{ $candidate->name }}</strong>
                                                </td>
                                                <td>
                                                    {{ $candidate->member->first_name }} {{ $candidate->member->last_name }}
                                                    <br><small class="text-muted">{{ $candidate->member->member_no }}</small>
                                                </td>
                                                <td>
                                                    {{ Str::limit($candidate->bio, 100) }}
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        @if($candidate->manifesto)
                                                            <button type="button" class="btn btn-info btn-sm" 
                                                                    data-toggle="modal" data-target="#manifestoModal{{ $candidate->id }}"
                                                                    title="{{ _lang('View Manifesto') }}">
                                                                <i class="fas fa-file-alt"></i>
                                                            </button>
                                                        @endif
                                                        
                                                        <form method="POST" action="{{ route('voting.candidates.remove', $candidate->id) }}" 
                                                              style="display: inline;"
                                                              onsubmit="return confirm('{{ _lang('Are you sure you want to remove this candidate?') }}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                                    title="{{ _lang('Remove Candidate') }}">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">{{ _lang('No candidates added yet') }}</h5>
                                    <p class="text-muted">{{ _lang('Add candidates using the form above') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
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
    const memberSelect = document.getElementById('member_id');
    const nameInput = document.getElementById('name');
    
    memberSelect.addEventListener('change', function() {
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            const memberName = selectedOption.text.split(' (')[0]; // Extract name before member number
            nameInput.value = memberName;
        } else {
            nameInput.value = '';
        }
    });
});
</script>

<style>
.white-space-pre-line {
    white-space: pre-line;
}
</style>
@endsection
