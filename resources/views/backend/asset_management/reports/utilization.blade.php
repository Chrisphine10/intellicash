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
                        <li class="breadcrumb-item active">{{ _lang('Utilization Report') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Management - Utilization Report') }}</h4>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('asset-reports.utilization') }}">
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
                                        <a href="{{ route('asset-reports.utilization') }}" class="btn btn-secondary">
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

    <!-- Utilization Summary -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-primary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-building font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-primary mb-1">{{ $utilizationStats['total_leasable_assets'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Leasable Assets') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-success bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-chart-line font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-success mb-1">{{ number_format($utilizationStats['average_utilization'], 1) }}%</h5>
                            <p class="text-muted mb-0">{{ _lang('Average Utilization') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-info bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-handshake font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-info mb-1">{{ $utilizationStats['total_active_leases'] }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Active Leases') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assets Utilization Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Utilization Details') }}</h4>
                </div>
                <div class="card-body">
                    @if($reportData->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Asset Code') }}</th>
                                        <th>{{ _lang('Asset Name') }}</th>
                                        <th>{{ _lang('Category') }}</th>
                                        <th>{{ _lang('Utilization Rate') }}</th>
                                        <th>{{ _lang('Total Revenue') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Performance') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($reportData as $asset)
                                    <tr>
                                        <td><strong>{{ $asset['asset_code'] }}</strong></td>
                                        <td>{{ $asset['name'] }}</td>
                                        <td>{{ $asset['category'] }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar 
                                                        @if($asset['utilization_rate'] >= 80) bg-success
                                                        @elseif($asset['utilization_rate'] >= 60) bg-warning
                                                        @else bg-danger
                                                        @endif" 
                                                        role="progressbar" 
                                                        style="width: {{ $asset['utilization_rate'] }}%"
                                                        aria-valuenow="{{ $asset['utilization_rate'] }}" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="text-muted">{{ number_format($asset['utilization_rate'], 1) }}%</span>
                                            </div>
                                        </td>
                                        <td>{{ formatAmount($asset['total_revenue']) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $asset['utilization_rate'] >= 80 ? 'success' : ($asset['utilization_rate'] >= 60 ? 'warning' : 'danger') }}">
                                                @if($asset['utilization_rate'] >= 80)
                                                    {{ _lang('High') }}
                                                @elseif($asset['utilization_rate'] >= 60)
                                                    {{ _lang('Medium') }}
                                                @else
                                                    {{ _lang('Low') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            @if($asset['utilization_rate'] >= 80)
                                                <i class="fas fa-star text-success" title="{{ _lang('Excellent Performance') }}"></i>
                                            @elseif($asset['utilization_rate'] >= 60)
                                                <i class="fas fa-star-half-alt text-warning" title="{{ _lang('Good Performance') }}"></i>
                                            @else
                                                <i class="fas fa-star text-muted" title="{{ _lang('Needs Improvement') }}"></i>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No utilization data found') }}</h5>
                            <p class="text-muted">{{ _lang('No leasable assets were found for the selected date range.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Utilization Insights -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Utilization Insights') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">{{ _lang('Top Performing Assets') }}</h6>
                            <ul class="list-unstyled">
                                @foreach($utilizationStats['top_performers'] as $performer)
                                <li class="mb-2">
                                    <i class="fas fa-arrow-up text-success me-2"></i>
                                    <strong>{{ $performer['name'] }}</strong> - {{ number_format($performer['utilization_rate'], 1) }}%
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-warning">{{ _lang('Assets Needing Attention') }}</h6>
                            <ul class="list-unstyled">
                                @foreach($utilizationStats['underperformers'] as $underperformer)
                                <li class="mb-2">
                                    <i class="fas fa-arrow-down text-danger me-2"></i>
                                    <strong>{{ $underperformer['name'] }}</strong> - {{ number_format($underperformer['utilization_rate'], 1) }}%
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
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
