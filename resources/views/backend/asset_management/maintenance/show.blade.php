@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-management.dashboard') }}">{{ _lang('Asset Management') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-maintenance.index') }}">{{ _lang('Maintenance') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Maintenance Details') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Maintenance Details') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ $maintenance->title }}</h4>
                        <div>
                            @if($maintenance->status === 'scheduled')
                                <form action="{{ route('asset-maintenance.mark-in-progress', $maintenance) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-play me-1"></i> {{ _lang('Start Work') }}
                                    </button>
                                </form>
                                <form action="{{ route('asset-maintenance.cancel', $maintenance) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this maintenance?')">
                                        <i class="fas fa-times me-1"></i> {{ _lang('Cancel') }}
                                    </button>
                                </form>
                            @elseif($maintenance->status === 'in_progress')
                                <form action="{{ route('asset-maintenance.complete', $maintenance) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to complete this maintenance?')">
                                        <i class="fas fa-check me-1"></i> {{ _lang('Complete') }}
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('asset-maintenance.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> {{ _lang('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ _lang('Maintenance Information') }}</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Type') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-info">{{ ucfirst($maintenance->maintenance_type) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Asset') }}:</strong></td>
                                    <td>
                                        <a href="{{ route('assets.show', $maintenance->asset) }}">{{ $maintenance->asset->name }}</a>
                                        <br><small class="text-muted">{{ $maintenance->asset->asset_code }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Scheduled Date') }}:</strong></td>
                                    <td>{{ $maintenance->scheduled_date }}</td>
                                </tr>
                                @if($maintenance->completed_date)
                                <tr>
                                    <td><strong>{{ _lang('Completed Date') }}:</strong></td>
                                    <td>{{ $maintenance->completed_date }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-{{ $maintenance->status === 'completed' ? 'success' : ($maintenance->status === 'in_progress' ? 'warning' : ($maintenance->status === 'cancelled' ? 'danger' : 'secondary')) }}">
                                            {{ ucfirst($maintenance->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Cost') }}:</strong></td>
                                    <td>{{ formatAmount($maintenance->cost) }}</td>
                                </tr>
                                @if($maintenance->performed_by)
                                <tr>
                                    <td><strong>{{ _lang('Performed By') }}:</strong></td>
                                    <td>{{ $maintenance->performed_by }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ _lang('Description') }}</h6>
                            <p class="text-muted">
                                {{ $maintenance->description ?: _lang('No description provided') }}
                            </p>
                            
                            @if($maintenance->notes)
                            <h6 class="mt-3">{{ _lang('Notes') }}</h6>
                            <p class="text-muted">{{ $maintenance->notes }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Quick Actions') }}</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @if($maintenance->status === 'scheduled')
                            <form action="{{ route('asset-maintenance.mark-in-progress', $maintenance) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-play me-1"></i> {{ _lang('Start Work') }}
                                </button>
                            </form>
                            <form action="{{ route('asset-maintenance.cancel', $maintenance) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to cancel this maintenance?')">
                                    <i class="fas fa-times me-1"></i> {{ _lang('Cancel') }}
                                </button>
                            </form>
                        @elseif($maintenance->status === 'in_progress')
                            <form action="{{ route('asset-maintenance.complete', $maintenance) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Are you sure you want to complete this maintenance?')">
                                    <i class="fas fa-check me-1"></i> {{ _lang('Complete') }}
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('asset-maintenance.edit', $maintenance) }}" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> {{ _lang('Edit') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Asset Information -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h5>{{ $maintenance->asset->name }}</h5>
                        <p class="text-muted">{{ $maintenance->asset->asset_code }}</p>
                        <span class="badge badge-secondary">{{ $maintenance->asset->category->name }}</span>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h6 class="text-primary">{{ formatAmount($maintenance->asset->purchase_value) }}</h6>
                                <p class="text-muted mb-0">{{ _lang('Purchase Value') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h6 class="text-success">{{ formatAmount($maintenance->asset->current_value) }}</h6>
                            <p class="text-muted mb-0">{{ _lang('Current Value') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance History -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Recent Maintenance') }}</h4>
                </div>
                <div class="card-body">
                    @php
                        $recentMaintenance = $maintenance->asset->maintenance()
                                                          ->where('id', '!=', $maintenance->id)
                                                          ->orderBy('scheduled_date', 'desc')
                                                          ->take(3)
                                                          ->get();
                    @endphp
                    
                    @if($recentMaintenance->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($recentMaintenance as $recent)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $recent->title }}</h6>
                                        <p class="mb-1 text-muted">{{ $recent->scheduled_date }}</p>
                                        <small class="text-muted">{{ formatAmount($recent->cost) }}</small>
                                    </div>
                                    <span class="badge badge-{{ $recent->status === 'completed' ? 'success' : ($recent->status === 'in_progress' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($recent->status) }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-tools fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">{{ _lang('No other maintenance records') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
