@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-line me-2"></i>{{ _lang('Security Test Results') }}
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.security.dashboard') }}">{{ _lang('Security') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('security.testing') }}">{{ _lang('Testing') }}</a></li>
                <li class="breadcrumb-item active">{{ _lang('Results') }}</li>
            </ol>
        </nav>
    </div>

    @if(isset($results) && !empty($results))
        <!-- Test Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    {{ _lang('Passed Tests') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $results['overall']['passed'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    {{ _lang('Failed Tests') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $results['overall']['failed'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    {{ _lang('Total Tests') }}
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $results['overall']['total'] ?? 0 }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-list fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    {{ _lang('Success Rate') }}
                                </div>
                                <div class="row no-gutters align-items-center">
                                    <div class="col-auto">
                                        <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                            @php
                                                $total = $results['overall']['total'] ?? 0;
                                                $passed = $results['overall']['passed'] ?? 0;
                                                $successRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
                                            @endphp
                                            {{ $successRate }}%
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="progress progress-sm mr-2">
                                            <div class="progress-bar bg-primary" role="progressbar"
                                                 style="width: {{ $successRate }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-percentage fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Details by Category -->
        @if(isset($results['categories']) && !empty($results['categories']))
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-list-alt me-2"></i>{{ _lang('Test Results by Category') }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="testCategoriesAccordion">
                                @foreach($results['categories'] as $categoryName => $category)
                                    <div class="card mb-2">
                                        <div class="card-header" id="heading{{ $loop->index }}">
                                            <h5 class="mb-0">
                                                <button class="btn btn-link d-flex justify-content-between align-items-center w-100" 
                                                        type="button" 
                                                        data-bs-toggle="collapse" 
                                                        data-bs-target="#collapse{{ $loop->index }}"
                                                        style="text-decoration: none;">
                                                    <span>
                                                        <i class="fas fa-{{ $category['icon'] ?? 'fa-cog' }} me-2"></i>
                                                        {{ $categoryName }}
                                                    </span>
                                                    <span class="badge badge-{{ ($category['passed'] ?? 0) == ($category['total'] ?? 0) ? 'success' : (($category['passed'] ?? 0) > 0 ? 'warning' : 'danger') }}">
                                                        {{ $category['passed'] ?? 0 }}/{{ $category['total'] ?? 0 }}
                                                    </span>
                                                </button>
                                            </h5>
                                        </div>

                                        <div id="collapse{{ $loop->index }}" 
                                             class="collapse {{ $loop->first ? 'show' : '' }}" 
                                             data-bs-parent="#testCategoriesAccordion">
                                            <div class="card-body">
                                                @if(isset($category['tests']) && !empty($category['tests']))
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th width="5%">{{ _lang('Status') }}</th>
                                                                    <th width="70%">{{ _lang('Test Name') }}</th>
                                                                    <th width="15%">{{ _lang('Duration') }}</th>
                                                                    <th width="10%">{{ _lang('Details') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($category['tests'] as $test)
                                                                    <tr>
                                                                        <td>
                                                                            @if($test['status'] ?? false)
                                                                                <i class="fas fa-check-circle text-success" title="{{ _lang('Passed') }}"></i>
                                                                            @else
                                                                                <i class="fas fa-times-circle text-danger" title="{{ _lang('Failed') }}"></i>
                                                                            @endif
                                                                        </td>
                                                                        <td>{{ $test['name'] ?? 'Unknown Test' }}</td>
                                                                        <td>
                                                                            <span class="badge badge-light">
                                                                                {{ number_format(($test['duration'] ?? 0) * 1000, 2) }}ms
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            @if(isset($test['message']) && !empty($test['message']))
                                                                                <button class="btn btn-sm btn-outline-info" 
                                                                                        type="button" 
                                                                                        data-bs-toggle="tooltip" 
                                                                                        title="{{ $test['message'] }}">
                                                                                    <i class="fas fa-info"></i>
                                                                                </button>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="text-muted">{{ _lang('No test details available for this category.') }}</p>
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

        <!-- Test Metadata -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-info-circle me-2"></i>{{ _lang('Test Information') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">{{ _lang('Test Type:') }}</dt>
                                    <dd class="col-sm-7">
                                        <span class="badge badge-info">{{ ucfirst($results['test_type'] ?? 'Unknown') }}</span>
                                    </dd>
                                    <dt class="col-sm-5">{{ _lang('Start Time:') }}</dt>
                                    <dd class="col-sm-7">{{ isset($results['start_time']) ? \Carbon\Carbon::parse($results['start_time'])->format('M j, Y H:i:s') : 'N/A' }}</dd>
                                    <dt class="col-sm-5">{{ _lang('End Time:') }}</dt>
                                    <dd class="col-sm-7">{{ isset($results['end_time']) ? \Carbon\Carbon::parse($results['end_time'])->format('M j, Y H:i:s') : 'N/A' }}</dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-5">{{ _lang('Duration:') }}</dt>
                                    <dd class="col-sm-7">
                                        @if(isset($results['duration']))
                                            {{ $results['duration'] }} {{ _lang('seconds') }}
                                        @else
                                            N/A
                                        @endif
                                    </dd>
                                    <dt class="col-sm-5">{{ _lang('Environment:') }}</dt>
                                    <dd class="col-sm-7">{{ config('app.env', 'Unknown') }}</dd>
                                    <dt class="col-sm-5">{{ _lang('Tested By:') }}</dt>
                                    <dd class="col-sm-7">{{ auth()->user()->name ?? 'Unknown' }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @else
        <!-- No Results State -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">{{ _lang('No Test Results Found') }}</h4>
                        <p class="text-muted">{{ _lang('Run security tests to see detailed results here.') }}</p>
                        <a href="{{ route('security.testing') }}" class="btn btn-primary btn-lg">
                            <i class="fas fa-play me-2"></i>{{ _lang('Run Tests') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Action Buttons -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <a href="{{ route('security.testing') }}" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>{{ _lang('Back to Testing') }}
            </a>
            @if(isset($results) && !empty($results))
                <a href="{{ route('security.testing.history') }}" class="btn btn-info btn-lg ml-2">
                    <i class="fas fa-history me-2"></i>{{ _lang('View History') }}
                </a>
                <button class="btn btn-primary btn-lg ml-2" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>{{ _lang('Print Results') }}
                </button>
            @endif
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.progress-sm {
    height: 0.5rem;
}

@media print {
    .btn, .breadcrumb {
        display: none !important;
    }
}
</style>
@endsection

@section('scripts')
<script>
// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endsection
