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
                        <li class="breadcrumb-item"><a href="{{ route('asset-reports.index') }}">{{ _lang('Reports') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Maintenance Report') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Management - Maintenance Report') }}</h4>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('asset-reports.maintenance') }}">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date">{{ _lang('Start Date') }}</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="{{ $startDate }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end_date">{{ _lang('End Date') }}</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="{{ $endDate }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">{{ _lang('Status') }}</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">{{ _lang('All Statuses') }}</option>
                                        <option value="scheduled" {{ $status === 'scheduled' ? 'selected' : '' }}>{{ _lang('Scheduled') }}</option>
                                        <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>{{ _lang('In Progress') }}</option>
                                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>{{ _lang('Completed') }}</option>
                                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>{{ _lang('Cancelled') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i> {{ _lang('Filter') }}
                                        </button>
                                        <a href="{{ route('asset-reports.maintenance') }}" class="btn btn-secondary">
                                            <i class="fas fa-refresh me-1"></i> {{ _lang('Reset') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Statistics -->
    <div class="row">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-primary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-calendar-check font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-primary mb-1">{{ $maintenanceStats['total_scheduled'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Scheduled') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-success bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-check font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-success mb-1">{{ $maintenanceStats['total_completed'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Completed') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-info bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-clock font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-info mb-1">{{ $maintenanceStats['total_in_progress'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('In Progress') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-warning bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-dollar-sign font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-warning mb-1">{{ formatAmount($maintenanceStats['total_cost']) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Cost') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Records Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Maintenance Records') }}</h4>
                </div>
                <div class="card-body">
                    @if($maintenanceRecords->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('ID') }}</th>
                                        <th>{{ _lang('Asset') }}</th>
                                        <th>{{ _lang('Type') }}</th>
                                        <th>{{ _lang('Title') }}</th>
                                        <th>{{ _lang('Scheduled Date') }}</th>
                                        <th>{{ _lang('Completed Date') }}</th>
                                        <th>{{ _lang('Cost') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Performed By') }}</th>
                                        <th>{{ _lang('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($maintenanceRecords as $maintenance)
                                    <tr>
                                        <td><strong>#{{ $maintenance->id }}</strong></td>
                                        <td>
                                            <div>
                                                <strong>{{ $maintenance->asset->name }}</strong>
                                                <br><small class="text-muted">{{ $maintenance->asset->asset_code }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $maintenance->maintenance_type === 'emergency' ? 'danger' : ($maintenance->maintenance_type === 'scheduled' ? 'primary' : 'info') }}">
                                                {{ ucfirst(str_replace('_', ' ', $maintenance->maintenance_type)) }}
                                            </span>
                                        </td>
                                        <td>{{ $maintenance->title }}</td>
                                        <td>{{ $maintenance->scheduled_date }}</td>
                                        <td>{{ $maintenance->completed_date ?? '-' }}</td>
                                        <td>{{ formatAmount($maintenance->cost) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $maintenance->status === 'completed' ? 'success' : ($maintenance->status === 'in_progress' ? 'info' : ($maintenance->status === 'cancelled' ? 'danger' : 'warning')) }}">
                                                {{ ucfirst($maintenance->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $maintenance->performed_by ?? '-' }}</td>
                                        <td>
                                            <a href="{{ route('asset-maintenance.show', $maintenance) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No maintenance records found') }}</h5>
                            <p class="text-muted">{{ _lang('No maintenance records were found for the selected criteria.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ _lang('Export Report') }}</h5>
                        <div>
                            <button class="btn btn-success me-2" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> {{ _lang('Export PDF') }}
                            </button>
                            <button class="btn btn-primary" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i> {{ _lang('Export Excel') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function exportToPDF() {
        // Implementation for PDF export
        alert('PDF export functionality will be implemented');
    }

    function exportToExcel() {
        // Implementation for Excel export
        alert('Excel export functionality will be implemented');
    }
</script>
@endpush
