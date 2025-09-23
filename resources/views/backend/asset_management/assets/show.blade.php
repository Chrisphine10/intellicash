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
                        <li class="breadcrumb-item"><a href="{{ route('assets.index') }}">{{ _lang('Assets') }}</a></li>
                        <li class="breadcrumb-item active">{{ $asset->name }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Details') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ $asset->name }}</h4>
                        <div>
                            <a href="{{ route('assets.edit', $asset) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit me-1"></i> {{ _lang('Edit') }}
                            </a>
                            <a href="{{ route('assets.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> {{ _lang('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ _lang('Asset Information') }}</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Asset Code') }}:</strong></td>
                                    <td>{{ $asset->asset_code }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Category') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-secondary">{{ $asset->category->name }}</span>
                                        <br><small class="text-muted">{{ ucfirst($asset->category->type) }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($asset->status) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Purchase Value') }}:</strong></td>
                                    <td>{{ formatAmount($asset->purchase_value) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Current Value') }}:</strong></td>
                                    <td>{{ formatAmount($asset->current_value) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Purchase Date') }}:</strong></td>
                                    <td>{{ $asset->purchase_date }}</td>
                                </tr>
                                @if($asset->warranty_expiry)
                                <tr>
                                    <td><strong>{{ _lang('Warranty Expiry') }}:</strong></td>
                                    <td>{{ $asset->warranty_expiry }}</td>
                                </tr>
                                @endif
                                @if($asset->location)
                                <tr>
                                    <td><strong>{{ _lang('Location') }}:</strong></td>
                                    <td>{{ $asset->location }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ _lang('Lease Information') }}</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Leasable') }}:</strong></td>
                                    <td>
                                        @if($asset->is_leasable)
                                            <span class="badge badge-success">{{ _lang('Yes') }}</span>
                                        @else
                                            <span class="badge badge-secondary">{{ _lang('No') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($asset->is_leasable)
                                <tr>
                                    <td><strong>{{ _lang('Lease Rate') }}:</strong></td>
                                    <td>{{ formatAmount($asset->lease_rate) }} / {{ ucfirst($asset->lease_rate_type) }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td><strong>{{ _lang('Total Revenue') }}:</strong></td>
                                    <td>{{ formatAmount($asset->total_lease_revenue) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Current Lease') }}:</strong></td>
                                    <td>
                                        @if($asset->current_lease)
                                            <span class="badge badge-info">{{ _lang('Leased') }}</span>
                                        @else
                                            <span class="badge badge-success">{{ _lang('Available') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($asset->description)
                    <div class="mt-3">
                        <h6>{{ _lang('Description') }}</h6>
                        <p class="text-muted">{{ $asset->description }}</p>
                    </div>
                    @endif

                    @if($asset->notes)
                    <div class="mt-3">
                        <h6>{{ _lang('Notes') }}</h6>
                        <p class="text-muted">{{ $asset->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Current Lease -->
            @if($asset->current_lease)
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Current Lease') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Member') }}:</strong></td>
                                    <td>{{ $asset->current_lease->member->first_name }} {{ $asset->current_lease->member->last_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Start Date') }}:</strong></td>
                                    <td>{{ $asset->current_lease->start_date }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('End Date') }}:</strong></td>
                                    <td>{{ $asset->current_lease->end_date }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Daily Rate') }}:</strong></td>
                                    <td>{{ formatAmount($asset->current_lease->daily_rate) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Total Amount') }}:</strong></td>
                                    <td>{{ formatAmount($asset->current_lease->total_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Deposit') }}:</strong></td>
                                    <td>{{ formatAmount($asset->current_lease->deposit) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-{{ $asset->current_lease->status === 'active' ? 'success' : 'secondary' }}">
                                            {{ ucfirst($asset->current_lease->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Maintenance History -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Maintenance History') }}</h4>
                </div>
                <div class="card-body">
                    @if($asset->maintenance->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Date') }}</th>
                                        <th>{{ _lang('Type') }}</th>
                                        <th>{{ _lang('Title') }}</th>
                                        <th>{{ _lang('Cost') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($asset->maintenance->take(5) as $maintenance)
                                    <tr>
                                        <td>{{ $maintenance->scheduled_date }}</td>
                                        <td>
                                            <span class="badge badge-info">{{ ucfirst($maintenance->maintenance_type) }}</span>
                                        </td>
                                        <td>{{ $maintenance->title }}</td>
                                        <td>{{ formatAmount($maintenance->cost) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $maintenance->status === 'completed' ? 'success' : ($maintenance->status === 'in_progress' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($maintenance->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($asset->maintenance->count() > 5)
                            <div class="text-center mt-3">
                                <a href="{{ route('asset-maintenance.index', ['asset_id' => $asset->id]) }}" class="btn btn-sm btn-outline-primary">
                                    {{ _lang('View All Maintenance Records') }}
                                </a>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No maintenance records') }}</h5>
                            <p class="text-muted">{{ _lang('Maintenance records will appear here once they are added.') }}</p>
                        </div>
                    @endif
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
                        @if($asset->is_leasable && $asset->isAvailableForLease())
                            <a href="{{ route('assets.lease-form', $asset) }}" class="btn btn-success">
                                <i class="fas fa-handshake me-1"></i> {{ _lang('Create Lease') }}
                            </a>
                        @endif
                        <a href="{{ route('asset-maintenance.create', ['asset_id' => $asset->id]) }}" class="btn btn-warning">
                            <i class="fas fa-tools me-1"></i> {{ _lang('Schedule Maintenance') }}
                        </a>
                        <a href="{{ route('assets.edit', $asset) }}" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> {{ _lang('Edit Asset') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Asset Statistics -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Statistics') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-primary">{{ $asset->leases->count() }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Total Leases') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success">{{ formatAmount($asset->total_lease_revenue) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Total Revenue') }}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-warning">{{ $asset->maintenance->count() }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Maintenance Records') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-danger">{{ formatAmount($asset->maintenance->sum('cost')) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Maintenance Cost') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
