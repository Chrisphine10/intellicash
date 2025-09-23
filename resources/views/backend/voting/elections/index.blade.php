@extends('layouts.app')

@section('title', _lang('Elections Management'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="card-title">{{ _lang('Elections Management') }}</h3>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="{{ route('voting.elections.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> {{ _lang('Create Election') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select class="form-control" id="status-filter">
                                <option value="">{{ _lang('All Status') }}</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ _lang('Draft') }}</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ _lang('Active') }}</option>
                                <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ _lang('Closed') }}</option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ _lang('Cancelled') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="type-filter">
                                <option value="">{{ _lang('All Types') }}</option>
                                <option value="single_winner" {{ request('type') == 'single_winner' ? 'selected' : '' }}>{{ _lang('Single Winner') }}</option>
                                <option value="multi_position" {{ request('type') == 'multi_position' ? 'selected' : '' }}>{{ _lang('Multi Position') }}</option>
                                <option value="referendum" {{ request('type') == 'referendum' ? 'selected' : '' }}>{{ _lang('Referendum') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-secondary" onclick="applyFilters()">
                                <i class="fas fa-filter"></i> {{ _lang('Apply Filters') }}
                            </button>
                        </div>
                    </div>

                    <!-- Elections Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Title') }}</th>
                                    <th>{{ _lang('Type') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Start Date') }}</th>
                                    <th>{{ _lang('End Date') }}</th>
                                    <th>{{ _lang('Participation') }}</th>
                                    <th>{{ _lang('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($elections as $election)
                                <tr>
                                    <td>
                                        <strong>{{ $election->title }}</strong>
                                        @if($election->position)
                                            <br><small class="text-muted">{{ $election->position->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            {{ ucfirst(str_replace('_', ' ', $election->type)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @switch($election->status)
                                            @case('draft')
                                                <span class="badge badge-secondary">{{ _lang('Draft') }}</span>
                                                @break
                                            @case('active')
                                                <span class="badge badge-success">{{ _lang('Active') }}</span>
                                                @break
                                            @case('closed')
                                                <span class="badge badge-dark">{{ _lang('Closed') }}</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge badge-danger">{{ _lang('Cancelled') }}</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>{{ $election->start_date->format('M d, Y H:i') }}</td>
                                    <td>{{ $election->end_date->format('M d, Y H:i') }}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: {{ $election->participation_rate }}%"
                                                 aria-valuenow="{{ $election->participation_rate }}" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                                {{ number_format($election->participation_rate, 1) }}%
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            {{ $election->total_votes }} {{ _lang('votes') }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('voting.elections.show', $election->id) }}" 
                                               class="btn btn-info btn-sm" title="{{ _lang('View') }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            @if($election->status === 'draft')
                                                <a href="{{ route('voting.elections.edit', $election->id) }}" 
                                                   class="btn btn-warning btn-sm" title="{{ _lang('Edit') }}">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" action="{{ route('voting.elections.start', $election->id) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success btn-sm" 
                                                            title="{{ _lang('Start Election') }}"
                                                            onclick="return confirm('{{ _lang('Are you sure you want to start this election?') }}')">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            @if($election->status === 'active')
                                                <form method="POST" action="{{ route('voting.elections.close', $election->id) }}" 
                                                      style="display: inline;">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-sm" 
                                                            title="{{ _lang('Close Election') }}"
                                                            onclick="return confirm('{{ _lang('Are you sure you want to close this election?') }}')">
                                                        <i class="fas fa-stop"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            
                                            @if($election->status === 'closed')
                                                <a href="{{ route('voting.elections.results', $election->id) }}" 
                                                   class="btn btn-primary btn-sm" title="{{ _lang('View Results') }}">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">{{ _lang('No elections found') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $elections->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const status = document.getElementById('status-filter').value;
    const type = document.getElementById('type-filter').value;
    
    const url = new URL(window.location);
    if (status) url.searchParams.set('status', status);
    else url.searchParams.delete('status');
    
    if (type) url.searchParams.set('type', type);
    else url.searchParams.delete('type');
    
    window.location.href = url.toString();
}
</script>
@endsection
