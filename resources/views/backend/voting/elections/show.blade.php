@extends('layouts.app')

@section('title', _lang('Election Details'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h3 class="card-title">{{ $election->title }}</h3>
                            @if($election->position)
                                <p class="text-muted mb-0">{{ $election->position->name }}</p>
                            @endif
                        </div>
                        <div class="col-md-4 text-right">
                            <div class="btn-group">
                                @if($election->status === 'draft')
                                    <a href="{{ route('voting.elections.edit', $election->id) }}" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('voting.elections.start', $election->id) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-success" 
                                                onclick="return confirm('{{ _lang('Are you sure you want to start this election?') }}')">
                                            <i class="fas fa-play"></i> {{ _lang('Start Election') }}
                                        </button>
                                    </form>
                                @elseif($election->status === 'active')
                                    <a href="{{ route('voting.elections.vote', $election->id) }}" class="btn btn-primary">
                                        <i class="fas fa-vote-yea"></i> {{ _lang('Vote Now') }}
                                    </a>
                                    <form method="POST" action="{{ route('voting.elections.close', $election->id) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-danger" 
                                                onclick="return confirm('{{ _lang('Are you sure you want to close this election?') }}')">
                                            <i class="fas fa-stop"></i> {{ _lang('Close Election') }}
                                        </button>
                                    </form>
                                @elseif($election->status === 'closed')
                                    <a href="{{ route('voting.elections.results', $election->id) }}" class="btn btn-info">
                                        <i class="fas fa-chart-bar"></i> {{ _lang('View Results') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Election Status and Info -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Election Information') }}</h5>
                                </div>
                                <div class="card-body">
                                    @if($election->description)
                                        <p><strong>{{ _lang('Description:') }}</strong> {{ $election->description }}</p>
                                    @endif
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>{{ _lang('Type:') }}</strong> 
                                                <span class="badge badge-info">{{ ucfirst(str_replace('_', ' ', $election->type)) }}</span>
                                            </p>
                                            <p><strong>{{ _lang('Voting Mechanism:') }}</strong> 
                                                {{ ucfirst(str_replace('_', ' ', $election->voting_mechanism)) }}
                                            </p>
                                            <p><strong>{{ _lang('Privacy Mode:') }}</strong> 
                                                <span class="badge badge-secondary">{{ ucfirst($election->privacy_mode) }}</span>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>{{ _lang('Start Date:') }}</strong> {{ $election->start_date->format('M d, Y H:i') }}</p>
                                            <p><strong>{{ _lang('End Date:') }}</strong> {{ $election->end_date->format('M d, Y H:i') }}</p>
                                            <p><strong>{{ _lang('Created By:') }}</strong> {{ $election->createdBy ? $election->createdBy->name : _lang('System') }}</p>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <p><strong>{{ _lang('Options:') }}</strong></p>
                                            <ul class="list-inline">
                                                @if($election->allow_abstain)
                                                    <li class="list-inline-item">
                                                        <span class="badge badge-success">{{ _lang('Allow Abstain') }}</span>
                                                    </li>
                                                @endif
                                                @if($election->weighted_voting)
                                                    <li class="list-inline-item">
                                                        <span class="badge badge-warning">{{ _lang('Weighted Voting') }}</span>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Statistics') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <strong>{{ _lang('Participation Rate:') }}</strong>
                                        <div class="progress mt-1" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: {{ $stats['participation_rate'] }}%"
                                                 aria-valuenow="{{ $stats['participation_rate'] }}" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                {{ number_format($stats['participation_rate'], 1) }}%
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p><strong>{{ _lang('Total Members:') }}</strong> {{ $stats['total_members'] }}</p>
                                    <p><strong>{{ _lang('Total Votes:') }}</strong> {{ $stats['total_votes'] }}</p>
                                    <p><strong>{{ _lang('Abstentions:') }}</strong> {{ $stats['abstentions'] }}</p>
                                    <p><strong>{{ _lang('Remaining:') }}</strong> {{ $stats['remaining_votes'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Candidates Section -->
                    @if($election->type !== 'referendum' && $election->candidates->count() > 0)
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>{{ _lang('Candidates') }} ({{ $election->candidates->count() }})</h5>
                                        </div>
                                        @if($election->status === 'draft')
                                        <div class="col-md-6 text-right">
                                            <a href="{{ route('voting.candidates.manage', $election->id) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-users"></i> {{ _lang('Manage Candidates') }}
                                            </a>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        @foreach($election->candidates as $candidate)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center">
                                                        @if($candidate->photo)
                                                            <img src="{{ asset('uploads/' . $candidate->photo) }}" 
                                                                 class="rounded-circle mr-3" width="50" height="50" 
                                                                 alt="{{ $candidate->name }}">
                                                        @else
                                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mr-3" 
                                                                 style="width: 50px; height: 50px;">
                                                                <i class="fas fa-user text-white"></i>
                                                            </div>
                                                        @endif
                                                        <div>
                                                            <h6 class="mb-0">{{ $candidate->name }}</h6>
                                                            <small class="text-muted">{{ $candidate->member->first_name }} {{ $candidate->member->last_name }}</small>
                                                        </div>
                                                    </div>
                                                    
                                                    @if($candidate->bio)
                                                        <p class="mt-2 small">{{ Str::limit($candidate->bio, 100) }}</p>
                                                    @endif
                                                    
                                                    @if($election->status === 'closed')
                                                        <div class="mt-2">
                                                            <strong>{{ _lang('Votes:') }}</strong> {{ $candidate->vote_count }}
                                                            <span class="text-muted">({{ number_format($candidate->vote_percentage, 1) }}%)</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Privacy Notice for Private Elections -->
                    @if($election->privacy_mode === 'private' && auth()->user()->user_type === 'admin')
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-shield-alt"></i>
                                <strong>{{ _lang('Privacy Notice:') }}</strong>
                                {{ _lang('This election is set to private mode. Individual votes are not visible to maintain voter anonymity. Only election results and statistics are shown.') }}
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Voting History (privacy-aware) -->
                    @if(auth()->user()->user_type === 'admin' && $election->votes->count() > 0 && $election->privacy_mode !== 'private')
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Voting History') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>{{ _lang('Member') }}</th>
                                                    <th>{{ _lang('Choice') }}</th>
                                                    <th>{{ _lang('Voted At') }}</th>
                                                    @if($election->weighted_voting)
                                                        <th>{{ _lang('Weight') }}</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($election->votes as $vote)
                                                <tr>
                                                    <td>{{ $vote->member->first_name }} {{ $vote->member->last_name }}</td>
                                                    <td>
                                                        @if($vote->is_abstain)
                                                            <span class="badge badge-secondary">{{ _lang('Abstain') }}</span>
                                                        @elseif($election->type === 'referendum')
                                                            <span class="badge badge-{{ $vote->choice === 'yes' ? 'success' : 'danger' }}">
                                                                {{ ucfirst($vote->choice) }}
                                                            </span>
                                                        @else
                                                            {{ $vote->candidate->name }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $vote->voted_at->format('M d, Y H:i') }}</td>
                                                    @if($election->weighted_voting)
                                                        <td>{{ $vote->weight }}</td>
                                                    @endif
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
