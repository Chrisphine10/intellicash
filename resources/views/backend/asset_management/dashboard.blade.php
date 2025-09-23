@extends('layouts.app')

@section('content')
<div class="row">
    <!-- Overview Cards -->
    <div class="col-lg-3 col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-building fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-right">
                            <h3 class="mb-0">{{ $totalAssets }}</h3>
                            <p class="mb-0">{{ _lang('Total Assets') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-handshake fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-right">
                            <h3 class="mb-0">{{ $activeLeases }}</h3>
                            <p class="mb-0">{{ _lang('Active Leases') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-wrench fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-right">
                            <h3 class="mb-0">{{ $pendingMaintenance }}</h3>
                            <p class="mb-0">{{ _lang('Pending Maintenance') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="text-right">
                            <h3 class="mb-0">{{ formatAmount($totalValue) }}</h3>
                            <p class="mb-0">{{ _lang('Total Asset Value') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ _lang('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('assets.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> {{ _lang('Add New Asset') }}
                    </a>
                    <a href="{{ route('asset-categories.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-tags"></i> {{ _lang('Manage Categories') }}
                    </a>
                    <a href="{{ route('assets.available-for-lease') }}" class="btn btn-outline-success">
                        <i class="fas fa-handshake"></i> {{ _lang('Available for Lease') }}
                    </a>
                    <a href="{{ route('asset-maintenance.overdue') }}" class="btn btn-outline-warning">
                        <i class="fas fa-exclamation-triangle"></i> {{ _lang('Overdue Maintenance') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Asset Categories Distribution -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ _lang('Assets by Category') }}</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ _lang('Category') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th class="text-center">{{ _lang('Assets') }}</th>
                                <th class="text-center">{{ _lang('Leasable') }}</th>
                                <th class="text-right">{{ _lang('Total Value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categoryStats as $stat)
                            <tr>
                                <td>
                                    <strong>{{ $stat->name }}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">{{ ucfirst($stat->type) }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-primary">{{ $stat->assets_count }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-success">{{ $stat->leasable_count }}</span>
                                </td>
                                <td class="text-right">
                                    {{ formatAmount($stat->total_value) }}
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

<div class="row">
    <!-- Recent Assets -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ _lang('Recent Assets') }}</h5>
                <a href="{{ route('assets.index') }}" class="btn btn-sm btn-outline-primary">{{ _lang('View All') }}</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ _lang('Asset') }}</th>
                                <th>{{ _lang('Category') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAssets as $asset)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $asset->name }}</strong>
                                        <br><small class="text-muted">{{ $asset->asset_code }}</small>
                                    </div>
                                </td>
                                <td>{{ $asset->category->name }}</td>
                                <td>
                                    @if($asset->status == 'active')
                                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                                    @elseif($asset->status == 'maintenance')
                                        <span class="badge badge-warning">{{ _lang('Maintenance') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ ucfirst($asset->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ formatAmount($asset->current_value) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Leases -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ _lang('Active Leases') }}</h5>
                <a href="{{ route('asset-leases.index') }}" class="btn btn-sm btn-outline-primary">{{ _lang('View All') }}</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ _lang('Asset') }}</th>
                                <th>{{ _lang('Member') }}</th>
                                <th>{{ _lang('Start Date') }}</th>
                                <th>{{ _lang('Daily Rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLeases as $lease)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $lease->asset->name }}</strong>
                                        <br><small class="text-muted">{{ $lease->lease_number }}</small>
                                    </div>
                                </td>
                                <td>{{ $lease->member->first_name }} {{ $lease->member->last_name }}</td>
                                <td>{{ $lease->start_date }}</td>
                                <td>{{ formatAmount($lease->daily_rate) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
