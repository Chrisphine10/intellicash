@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('VSLA Cycle Management') }}</span>
                <a class="btn btn-primary btn-sm ml-auto" href="{{ route('vsla.cycles.create') }}">{{ _lang('Create New Cycle') }}</a>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">{{ _lang('All Statuses') }}</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ _lang('Active') }}</option>
                                <option value="share_out_in_progress" {{ request('status') == 'share_out_in_progress' ? 'selected' : '' }}>{{ _lang('Share-Out In Progress') }}</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ _lang('Completed') }}</option>
                                <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>{{ _lang('Archived') }}</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-light btn-sm">{{ _lang('Filter') }}</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ _lang('Cycle Name') }}</th>
                                <th>{{ _lang('Period') }}</th>
                                <th>{{ _lang('Phase') }}</th>
                                <th>{{ _lang('Total Shares') }}</th>
                                <th>{{ _lang('Available for Share-Out') }}</th>
                                <th>{{ _lang('Members') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cycles as $cycle)
                                <tr>
                                    <td>{{ $cycle->cycle_name }}</td>
                                    <td>
                                        {{ $cycle->start_date->format('M d, Y') }} - {{ $cycle->end_date->format('M d, Y') }}
                                        <br><small class="text-muted">{{ $cycle->getFormattedDuration() }}</small>
                                    </td>
                                    <td>
                                        @php $phase = $cycle->getCurrentPhase(); @endphp
                                        @if($phase == 'active')
                                            <span class="badge badge-success">{{ _lang('Active') }}</span>
                                            <br><small class="text-muted">{{ _lang('Collecting contributions') }}</small>
                                        @elseif($phase == 'ready_for_shareout')
                                            <span class="badge badge-info">{{ _lang('Ready for Share-Out') }}</span>
                                            <br><small class="text-muted">{{ _lang('Cycle ended, can start share-out') }}</small>
                                        @elseif($phase == 'share_out')
                                            <span class="badge badge-warning">{{ _lang('Share-Out In Progress') }}</span>
                                            <br><small class="text-muted">{{ _lang('Processing member payouts') }}</small>
                                        @elseif($phase == 'completed')
                                            <span class="badge badge-primary">{{ _lang('Completed') }}</span>
                                            <br><small class="text-muted">{{ _lang('Share-out distributed') }}</small>
                                        @else
                                            <span class="badge badge-secondary">{{ _lang('Archived') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ currency($cycle->total_shares_contributed) }}</td>
                                    <td>{{ currency($cycle->total_available_for_shareout) }}</td>
                                    <td>
                                        {{ $cycle->getParticipatingMembersCount() }}
                                        @if($cycle->shareouts->count() > 0)
                                            <br><small class="text-muted">{{ $cycle->shareouts->count() }} payouts</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                {{ _lang('Actions') }}
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="{{ route('vsla.cycles.show', $cycle->id) }}">{{ _lang('View Details') }}</a>
                                                
                                                @if($cycle->status == 'active' && $cycle->isEligibleForShareOut())
                                                    <a class="dropdown-item text-warning" href="{{ route('vsla.cycles.calculate', $cycle->id) }}" 
                                                       onclick="return confirm('{{ _lang('Are you sure you want to calculate share-out for this cycle?') }}')">
                                                        {{ _lang('Calculate Share-Out') }}
                                                    </a>
                                                @endif
                                                
                                                @if($cycle->status == 'share_out_in_progress')
                                                    <a class="dropdown-item text-success" href="{{ route('vsla.cycles.approve', $cycle->id) }}" 
                                                       onclick="return confirm('{{ _lang('Are you sure you want to approve these calculations?') }}')">
                                                        {{ _lang('Approve Calculations') }}
                                                    </a>
                                                    <a class="dropdown-item text-primary" href="{{ route('vsla.cycles.process_payout', $cycle->id) }}" 
                                                       onclick="return confirm('{{ _lang('Are you sure you want to process payouts? This action cannot be undone.') }}')">
                                                        {{ _lang('Process Payouts') }}
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item text-danger" href="{{ route('vsla.cycles.cancel', $cycle->id) }}" 
                                                       onclick="return confirm('{{ _lang('Are you sure you want to cancel share-out calculations?') }}')">
                                                        {{ _lang('Cancel Share-Out') }}
                                                    </a>
                                                @endif
                                                
                                                @if($cycle->status == 'completed')
                                                    <a class="dropdown-item" href="{{ route('vsla.cycles.export_report', $cycle->id) }}" target="_blank">
                                                        {{ _lang('Export Report') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">{{ _lang('No cycles found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $cycles->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
