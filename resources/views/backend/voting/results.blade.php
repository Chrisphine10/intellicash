@extends('layouts.app')

@section('title', _lang('Election Results'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-8">
                            <h3 class="card-title">{{ _lang('Election Results') }}</h3>
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
                    <!-- Election Summary -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Election Summary') }}</h5>
                                </div>
                                <div class="card-body">
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
                                            <p><strong>{{ _lang('Voting Period:') }}</strong></p>
                                            <p>{{ $election->start_date->format('M d, Y H:i') }} - {{ $election->end_date->format('M d, Y H:i') }}</p>
                                            <p><strong>{{ _lang('Closed:') }}</strong> {{ $election->updated_at->format('M d, Y H:i') }}</p>
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
                                    @php
                                        $totalVotes = $results->sum('total_votes');
                                        $totalMembers = \App\Models\Member::where('tenant_id', $election->tenant_id)->count();
                                        $participationRate = $totalMembers > 0 ? ($totalVotes / $totalMembers) * 100 : 0;
                                    @endphp
                                    
                                    <p><strong>{{ _lang('Total Members:') }}</strong> {{ $totalMembers }}</p>
                                    <p><strong>{{ _lang('Total Votes:') }}</strong> {{ $totalVotes }}</p>
                                    <p><strong>{{ _lang('Participation Rate:') }}</strong> {{ number_format($participationRate, 1) }}%</p>
                                    
                                    <div class="progress mt-2" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: {{ $participationRate }}%"
                                             aria-valuenow="{{ $participationRate }}" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            {{ number_format($participationRate, 1) }}%
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($election->type === 'referendum')
                        <!-- Referendum Results -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ _lang('Referendum Results') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $yesVotes = $results->where('choice', 'yes')->first();
                                            $noVotes = $results->where('choice', 'no')->first();
                                            $abstainVotes = $results->where('choice', 'abstain')->first();
                                        @endphp
                                        
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card text-center {{ $yesVotes && $yesVotes->is_winner ? 'border-success' : '' }}">
                                                    <div class="card-body">
                                                        <h3 class="text-success">
                                                            <i class="fas fa-check-circle"></i>
                                                        </h3>
                                                        <h4>{{ $yesVotes ? $yesVotes->total_votes : 0 }}</h4>
                                                        <p class="mb-0">{{ _lang('YES') }}</p>
                                                        <small class="text-muted">
                                                            {{ $yesVotes ? number_format($yesVotes->percentage, 1) : 0 }}%
                                                        </small>
                                                        @if($yesVotes && $yesVotes->is_winner)
                                                            <div class="mt-2">
                                                                <span class="badge badge-success badge-lg">{{ _lang('WINNER') }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <div class="card text-center {{ $noVotes && $noVotes->is_winner ? 'border-danger' : '' }}">
                                                    <div class="card-body">
                                                        <h3 class="text-danger">
                                                            <i class="fas fa-times-circle"></i>
                                                        </h3>
                                                        <h4>{{ $noVotes ? $noVotes->total_votes : 0 }}</h4>
                                                        <p class="mb-0">{{ _lang('NO') }}</p>
                                                        <small class="text-muted">
                                                            {{ $noVotes ? number_format($noVotes->percentage, 1) : 0 }}%
                                                        </small>
                                                        @if($noVotes && $noVotes->is_winner)
                                                            <div class="mt-2">
                                                                <span class="badge badge-danger badge-lg">{{ _lang('WINNER') }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            @if($abstainVotes)
                                            <div class="col-md-4">
                                                <div class="card text-center">
                                                    <div class="card-body">
                                                        <h3 class="text-secondary">
                                                            <i class="fas fa-minus-circle"></i>
                                                        </h3>
                                                        <h4>{{ $abstainVotes->total_votes }}</h4>
                                                        <p class="mb-0">{{ _lang('ABSTAIN') }}</p>
                                                        <small class="text-muted">
                                                            {{ number_format($abstainVotes->percentage, 1) }}%
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- Candidate Results -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ _lang('Election Results') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>{{ _lang('Rank') }}</th>
                                                        <th>{{ _lang('Candidate') }}</th>
                                                        <th>{{ _lang('Votes') }}</th>
                                                        <th>{{ _lang('Percentage') }}</th>
                                                        <th>{{ _lang('Status') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($results as $result)
                                                    <tr class="{{ $result->is_winner ? 'table-success' : '' }}">
                                                        <td>
                                                            @if($result->rank)
                                                                <span class="badge badge-primary badge-lg">{{ $result->rank }}</span>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mr-3" 
                                                                     style="width: 40px; height: 40px;">
                                                                    <i class="fas fa-user text-white"></i>
                                                                </div>
                                                                <div>
                                                                    <strong>{{ $result->candidate_name }}</strong>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <strong>{{ $result->total_votes }}</strong>
                                                        </td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar" role="progressbar" 
                                                                     style="width: {{ $result->percentage }}%"
                                                                     aria-valuenow="{{ $result->percentage }}" 
                                                                     aria-valuemin="0" aria-valuemax="100">
                                                                    {{ number_format($result->percentage, 1) }}%
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if($result->is_winner)
                                                                <span class="badge badge-success badge-lg">
                                                                    <i class="fas fa-trophy"></i> {{ _lang('WINNER') }}
                                                                </span>
                                                            @else
                                                                <span class="text-muted">{{ _lang('Not Selected') }}</span>
                                                            @endif
                                                        </td>
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

                    <!-- Detailed Breakdown -->
                    @if(auth()->user()->user_type === 'admin')
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5>{{ _lang('Detailed Breakdown') }}</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        @foreach($results as $result)
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card">
                                                <div class="card-body text-center">
                                                    <h6>{{ $result->candidate_name }}</h6>
                                                    <div class="mb-2">
                                                        <span class="h4 text-primary">{{ $result->total_votes }}</span>
                                                        <small class="text-muted"> {{ _lang('votes') }}</small>
                                                    </div>
                                                    <div class="mb-2">
                                                        <span class="h5">{{ number_format($result->percentage, 1) }}%</span>
                                                    </div>
                                                    @if($result->is_winner)
                                                        <span class="badge badge-success">{{ _lang('Winner') }}</span>
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
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
