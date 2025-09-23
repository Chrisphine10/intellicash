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
                        <li class="breadcrumb-item active">{{ _lang('Reports') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Management Reports') }}</h4>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-sm rounded bg-primary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-building font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1"><span data-plugin="counterup">{{ $totalAssets }}</span></h3>
                                <p class="text-muted mb-1">{{ _lang('Total Assets') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-sm rounded bg-success bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-dollar-sign font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1">{{ formatAmount($totalValue) }}</h3>
                                <p class="text-muted mb-1">{{ _lang('Total Value') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-sm rounded bg-info bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-handshake font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1"><span data-plugin="counterup">{{ $activeLeases }}</span></h3>
                                <p class="text-muted mb-1">{{ _lang('Active Leases') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="avatar-sm rounded bg-warning bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-tools font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <h3 class="text-dark mt-1"><span data-plugin="counterup">{{ $overdueMaintenance }}</span></h3>
                                <p class="text-muted mb-1">{{ _lang('Overdue Maintenance') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-primary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-chart-line font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1">{{ _lang('Asset Valuation Report') }}</h5>
                            <p class="text-muted mb-2">{{ _lang('Current asset values and depreciation') }}</p>
                            <a href="{{ route('asset-reports.valuation') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye me-1"></i> {{ _lang('View Report') }}
                            </a>
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
                                    <i class="fas fa-chart-pie font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1">{{ _lang('Profit & Loss Report') }}</h5>
                            <p class="text-muted mb-2">{{ _lang('Revenue, costs, and profitability') }}</p>
                            <a href="{{ route('asset-reports.profit-loss') }}" class="btn btn-success btn-sm">
                                <i class="fas fa-eye me-1"></i> {{ _lang('View Report') }}
                            </a>
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
                                    <i class="fas fa-chart-bar font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1">{{ _lang('Lease Performance') }}</h5>
                            <p class="text-muted mb-2">{{ _lang('Lease metrics and performance') }}</p>
                            <a href="{{ route('asset-reports.lease-performance') }}" class="btn btn-info btn-sm">
                                <i class="fas fa-eye me-1"></i> {{ _lang('View Report') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-warning bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-wrench font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1">{{ _lang('Maintenance Report') }}</h5>
                            <p class="text-muted mb-2">{{ _lang('Maintenance costs and schedules') }}</p>
                            <a href="{{ route('asset-reports.maintenance') }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-eye me-1"></i> {{ _lang('View Report') }}
                            </a>
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
                            <div class="avatar-sm rounded bg-secondary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-percentage font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-1">{{ _lang('Utilization Report') }}</h5>
                            <p class="text-muted mb-2">{{ _lang('Asset utilization rates') }}</p>
                            <a href="{{ route('asset-reports.utilization') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye me-1"></i> {{ _lang('View Report') }}
                            </a>
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
    $(document).ready(function() {
        // Initialize counter animation
        $('[data-plugin="counterup"]').each(function() {
            var $this = $(this);
            $this.counterUp({
                delay: 100,
                time: 1200
            });
        });
    });
</script>
@endpush
