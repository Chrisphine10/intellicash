@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Back Button -->
            <div class="mb-3">
                <a href="{{ route('asset-reports.index') }}" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> Back to Reports
                </a>
            </div>
            
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-management.dashboard') }}">{{ _lang('Asset Management') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-reports.index') }}">{{ _lang('Reports') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Lease Performance Report') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Management - Lease Performance Report') }}</h4>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('asset-reports.lease-performance') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="start_date">{{ _lang('Start Date') }}</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="{{ $startDate }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="end_date">{{ _lang('End Date') }}</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="{{ $endDate }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i> {{ _lang('Filter') }}
                                        </button>
                                        <a href="{{ route('asset-reports.lease-performance') }}" class="btn btn-secondary">
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

    <!-- Performance Metrics -->
    <div class="row">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-primary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-handshake font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-primary mb-1">{{ $performanceMetrics['total_leases'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Leases') }}</p>
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
                            <h5 class="text-success mb-1">{{ $performanceMetrics['completed_leases'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Completed Leases') }}</p>
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
                            <h5 class="text-info mb-1">{{ $performanceMetrics['active_leases'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Active Leases') }}</p>
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
                                    <i class="fas fa-times font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-warning mb-1">{{ $performanceMetrics['cancelled_leases'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Cancelled Leases') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue and Duration Metrics -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-success bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-dollar-sign font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-success mb-1">{{ formatAmount($performanceMetrics['total_revenue']) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Revenue') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-info bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-calendar font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-info mb-1">{{ number_format($performanceMetrics['average_lease_duration'], 1) }} {{ _lang('days') }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Average Duration') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leases Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Lease Details') }}</h4>
                </div>
                <div class="card-body">
                    @if($leases->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Lease ID') }}</th>
                                        <th>{{ _lang('Asset') }}</th>
                                        <th>{{ _lang('Member') }}</th>
                                        <th>{{ _lang('Start Date') }}</th>
                                        <th>{{ _lang('End Date') }}</th>
                                        <th>{{ _lang('Duration') }}</th>
                                        <th>{{ _lang('Daily Rate') }}</th>
                                        <th>{{ _lang('Total Amount') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($leases as $lease)
                                    <tr>
                                        <td>
                                            <strong>#{{ $lease->id }}</strong>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>{{ $lease->asset->name }}</strong>
                                                <br><small class="text-muted">{{ $lease->asset->asset_code }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong>{{ $lease->member->first_name }} {{ $lease->member->last_name }}</strong>
                                                <br><small class="text-muted">{{ $lease->member->member_id }}</small>
                                            </div>
                                        </td>
                                        <td>{{ $lease->start_date }}</td>
                                        <td>{{ $lease->end_date }}</td>
                                        <td>
                                            @php
                                                $start = \Carbon\Carbon::parse($lease->start_date);
                                                $end = \Carbon\Carbon::parse($lease->end_date);
                                                $duration = $start->diffInDays($end) + 1;
                                            @endphp
                                            {{ $duration }} {{ _lang('days') }}
                                        </td>
                                        <td>{{ formatAmount($lease->daily_rate) }}</td>
                                        <td>{{ formatAmount($lease->total_amount) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $lease->status === 'active' ? 'success' : ($lease->status === 'completed' ? 'primary' : 'danger') }}">
                                                {{ ucfirst($lease->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('asset-leases.show', $lease) }}" class="btn btn-sm btn-info">
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
                            <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No leases found') }}</h5>
                            <p class="text-muted">{{ _lang('No leases were found for the selected date range.') }}</p>
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
