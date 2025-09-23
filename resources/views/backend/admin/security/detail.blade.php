@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye me-2"></i>{{ _lang('Test Details') }} #{{ $testResult->id }}
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.security.dashboard') }}">{{ _lang('Security') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('security.testing') }}">{{ _lang('Testing') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('security.testing.history') }}">{{ _lang('History') }}</a></li>
                <li class="breadcrumb-item active">{{ _lang('Detail') }} #{{ $testResult->id }}</li>
            </ol>
        </nav>
    </div>

    <!-- Test Summary Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle me-2"></i>{{ _lang('Test Summary') }}
                    </h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow">
                            <a class="dropdown-item" href="{{ route('security.testing.history') }}">
                                <i class="fas fa-arrow-left fa-sm fa-fw mr-2 text-gray-400"></i>
                                {{ _lang('Back to History') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="deleteTest({{ $testResult->id }})">
                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-gray-400"></i>
                                {{ _lang('Delete Test') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">{{ _lang('Test ID:') }}</dt>
                                <dd class="col-sm-8"><span class="badge badge-info">#{{ $testResult->id }}</span></dd>
                                
                                <dt class="col-sm-4">{{ _lang('Test Type:') }}</dt>
                                <dd class="col-sm-8">
                                    <span class="badge badge-{{ $testResult->test_type == 'all' ? 'primary' : ($testResult->test_type == 'security' ? 'info' : ($testResult->test_type == 'financial' ? 'success' : 'secondary')) }}">
                                        {{ ucfirst($testResult->test_type) }}
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-4">{{ _lang('Total Tests:') }}</dt>
                                <dd class="col-sm-8"><span class="badge badge-info">{{ $testResult->total_tests }}</span></dd>
                                
                                <dt class="col-sm-4">{{ _lang('Passed:') }}</dt>
                                <dd class="col-sm-8"><span class="badge badge-success">{{ $testResult->passed_tests }}</span></dd>
                                
                                <dt class="col-sm-4">{{ _lang('Failed:') }}</dt>
                                <dd class="col-sm-8"><span class="badge badge-danger">{{ $testResult->failed_tests }}</span></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">{{ _lang('Overall Status:') }}</dt>
                                <dd class="col-sm-8">
                                    @if($testResult->success_rate >= 95)
                                        <span class="badge badge-success badge-lg">
                                            <i class="fas fa-trophy mr-1"></i>{{ _lang('Excellent') }} ({{ $testResult->success_rate }}%)
                                        </span>
                                    @elseif($testResult->success_rate >= 80)
                                        <span class="badge badge-success badge-lg">
                                            <i class="fas fa-check-circle mr-1"></i>{{ _lang('Good') }} ({{ $testResult->success_rate }}%)
                                        </span>
                                    @elseif($testResult->success_rate >= 60)
                                        <span class="badge badge-warning badge-lg">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>{{ _lang('Needs Attention') }} ({{ $testResult->success_rate }}%)
                                        </span>
                                    @else
                                        <span class="badge badge-danger badge-lg">
                                            <i class="fas fa-times-circle mr-1"></i>{{ _lang('Critical Issues') }} ({{ $testResult->success_rate }}%)
                                        </span>
                                    @endif
                                </dd>
                                
                                <dt class="col-sm-4">{{ _lang('Success Rate:') }}</dt>
                                <dd class="col-sm-8">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-{{ $testResult->success_rate >= 80 ? 'success' : ($testResult->success_rate >= 60 ? 'warning' : 'danger') }}" 
                                             style="width: {{ $testResult->success_rate }}%">
                                            {{ $testResult->success_rate }}%
                                        </div>
                                    </div>
                                </dd>
                                
                                <dt class="col-sm-4">{{ _lang('Duration:') }}</dt>
                                <dd class="col-sm-8"><span class="badge badge-light">{{ $testResult->duration_seconds }}s</span></dd>
                                
                                <dt class="col-sm-4">{{ _lang('Started:') }}</dt>
                                <dd class="col-sm-8">{{ $testResult->test_started_at ? $testResult->test_started_at->format('M j, Y H:i:s') : 'N/A' }}</dd>
                                
                                <dt class="col-sm-4">{{ _lang('Completed:') }}</dt>
                                <dd class="col-sm-8">{{ $testResult->test_completed_at ? $testResult->test_completed_at->format('M j, Y H:i:s') : 'N/A' }}</dd>
                                
                                <dt class="col-sm-4">{{ _lang('Tested By:') }}</dt>
                                <dd class="col-sm-8">{{ $testResult->user->name ?? 'Unknown' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Categories Results -->
    @if($testResult->test_summary && is_array($testResult->test_summary))
        <!-- Categories Summary Cards -->
        <div class="row mb-4">
            @foreach($testResult->test_summary as $categoryName => $category)
                @php
                    $successRate = ($category['total'] ?? 0) > 0 ? round((($category['passed'] ?? 0) / ($category['total'] ?? 0)) * 100, 1) : 0;
                    $categoryIcons = [
                        'Security Tests' => 'fa-shield-alt',
                        'Financial System Tests' => 'fa-coins',
                        'Calculation Accuracy Tests' => 'fa-calculator',
                        'Module Functionality Tests' => 'fa-cogs',
                        'Performance Tests' => 'fa-tachometer-alt',
                        'Compliance Tests (Banking Standards)' => 'fa-university'
                    ];
                    $icon = $categoryIcons[$categoryName] ?? 'fa-cog';
                    $cardColor = $successRate >= 80 ? 'success' : ($successRate >= 60 ? 'warning' : 'danger');
                @endphp
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card border-left-{{ $cardColor }} shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-{{ $cardColor }} text-uppercase mb-1">
                                        {{ $categoryName }}
                                    </div>
                                    <div class="row no-gutters align-items-center">
                                        <div class="col-auto">
                                            <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                                                {{ $category['passed'] ?? 0 }}/{{ $category['total'] ?? 0 }}
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="progress progress-sm mr-2">
                                                <div class="progress-bar bg-{{ $cardColor }}" role="progressbar" 
                                                     style="width: {{ $successRate }}%" aria-valuenow="{{ $successRate }}" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <span class="text-xs font-weight-bold">{{ $successRate }}%</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas {{ $icon }} fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list-alt me-2"></i>{{ _lang('Detailed Test Results by Category') }}
                        </h6>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-success mr-2">
                                <i class="fas fa-check-circle"></i> {{ _lang('Pass') }}
                            </span>
                            <span class="badge badge-danger">
                                <i class="fas fa-times-circle"></i> {{ _lang('Fail') }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="testCategoriesAccordion">
                            @foreach($testResult->test_summary as $categoryName => $category)
                                @php
                                    $categorySuccessRate = ($category['total'] ?? 0) > 0 ? round((($category['passed'] ?? 0) / ($category['total'] ?? 0)) * 100, 1) : 0;
                                    $categoryStatusColor = $categorySuccessRate >= 80 ? 'success' : ($categorySuccessRate >= 60 ? 'warning' : 'danger');
                                    $categoryStatusIcon = $categorySuccessRate == 100 ? 'fa-check-circle' : ($categorySuccessRate >= 80 ? 'fa-check-circle' : ($categorySuccessRate >= 60 ? 'fa-exclamation-triangle' : 'fa-times-circle'));
                                    $categoryIcons = [
                                        'Security Tests' => 'fa-shield-alt',
                                        'Financial System Tests' => 'fa-coins',
                                        'Calculation Accuracy Tests' => 'fa-calculator',
                                        'Module Functionality Tests' => 'fa-cogs',
                                        'Performance Tests' => 'fa-tachometer-alt',
                                        'Compliance Tests (Banking Standards)' => 'fa-university'
                                    ];
                                    $categoryIcon = $categoryIcons[$categoryName] ?? 'fa-cog';
                                @endphp
                                <div class="card mb-2 border-{{ $categoryStatusColor }}">
                                    <div class="card-header bg-light" id="heading{{ $loop->index }}">
                                        <h5 class="mb-0">
                                            <button class="btn btn-link d-flex justify-content-between align-items-center w-100" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse{{ $loop->index }}"
                                                    style="text-decoration: none;">
                                                <span class="d-flex align-items-center">
                                                    <i class="fas {{ $categoryIcon }} me-2 text-{{ $categoryStatusColor }}"></i>
                                                    <strong>{{ $categoryName }}</strong>
                                                    <i class="fas {{ $categoryStatusIcon }} text-{{ $categoryStatusColor }} ml-2"></i>
                                                </span>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge badge-{{ $categoryStatusColor }} mr-2">
                                                        {{ $category['passed'] ?? 0 }}/{{ $category['total'] ?? 0 }} ({{ $categorySuccessRate }}%)
                                                    </span>
                                                    @if($categorySuccessRate == 100)
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-trophy"></i> {{ _lang('Perfect') }}
                                                        </span>
                                                    @elseif($categorySuccessRate >= 80)
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-thumbs-up"></i> {{ _lang('Good') }}
                                                        </span>
                                                    @elseif($categorySuccessRate >= 60)
                                                        <span class="badge badge-warning">
                                                            <i class="fas fa-exclamation-triangle"></i> {{ _lang('Needs Attention') }}
                                                        </span>
                                                    @else
                                                        <span class="badge badge-danger">
                                                            <i class="fas fa-times-circle"></i> {{ _lang('Critical') }}
                                                        </span>
                                                    @endif
                                                </div>
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
                                                                <th width="60%">{{ _lang('Test Name') }}</th>
                                                                <th width="15%">{{ _lang('Duration') }}</th>
                                                                <th width="20%">{{ _lang('Details') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                            @foreach($category['tests'] as $test)
                                                @php
                                                    $testPassed = $test['status'] ?? false;
                                                    $duration = ($test['duration'] ?? 0) * 1000;
                                                    $durationColor = $duration < 100 ? 'success' : ($duration < 500 ? 'warning' : 'danger');
                                                @endphp
                                                <tr class="{{ $testPassed ? 'table-success' : 'table-danger' }}">
                                                    <td class="text-center">
                                                        @if($testPassed)
                                                            <span class="badge badge-success">
                                                                <i class="fas fa-check-circle"></i> {{ _lang('PASS') }}
                                                            </span>
                                                        @else
                                                            <span class="badge badge-danger">
                                                                <i class="fas fa-times-circle"></i> {{ _lang('FAIL') }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <strong>{{ $test['name'] ?? 'Unknown Test' }}</strong>
                                                        @if($testPassed)
                                                            <i class="fas fa-check-circle text-success ml-2" title="{{ _lang('Test Passed') }}"></i>
                                                        @else
                                                            <i class="fas fa-exclamation-triangle text-danger ml-2" title="{{ _lang('Test Failed') }}"></i>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-{{ $durationColor }}">
                                                            <i class="fas fa-clock mr-1"></i>{{ number_format($duration, 2) }}ms
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if(isset($test['message']) && !empty($test['message']))
                                                            <div class="alert alert-{{ $testPassed ? 'success' : 'danger' }} alert-sm mb-0 py-1 px-2">
                                                                <small>
                                                                    @if($testPassed)
                                                                        <i class="fas fa-info-circle mr-1"></i>
                                                                    @else
                                                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                                                    @endif
                                                                    {{ $test['message'] }}
                                                                </small>
                                                            </div>
                                                        @else
                                                            <small class="text-muted">
                                                                <i class="fas fa-check-circle text-success mr-1"></i>{{ _lang('Test completed successfully') }}
                                                            </small>
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

    <!-- Full Test Results JSON (Collapsible) -->
    @if($testResult->test_results)
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-code me-2"></i>{{ _lang('Raw Test Data') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="rawDataAccordion">
                            <div class="card">
                                <div class="card-header" id="rawDataHeading">
                                    <h5 class="mb-0">
                                        <button class="btn btn-link" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#rawDataCollapse" style="text-decoration: none;">
                                            <i class="fas fa-database me-2"></i>{{ _lang('View Raw JSON Data') }}
                                        </button>
                                    </h5>
                                </div>
                                <div id="rawDataCollapse" class="collapse" data-bs-parent="#rawDataAccordion">
                                    <div class="card-body">
                                        <pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"><code>{{ json_encode($testResult->test_results, JSON_PRETTY_PRINT) }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Action Buttons -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <a href="{{ route('security.testing.history') }}" class="btn btn-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>{{ _lang('Back to History') }}
            </a>
            <a href="{{ route('security.testing') }}" class="btn btn-primary btn-lg ml-2">
                <i class="fas fa-play me-2"></i>{{ _lang('Run New Test') }}
            </a>
            <button class="btn btn-info btn-lg ml-2" onclick="window.print()">
                <i class="fas fa-print me-2"></i>{{ _lang('Print Details') }}
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Confirm Delete') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{ _lang('Are you sure you want to delete this test result? This action cannot be undone.') }}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ _lang('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">{{ _lang('Delete') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
@media print {
    .btn, .breadcrumb, .dropdown {
        display: none !important;
    }
}

/* Enhanced Test Details Styling */
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}

.table-success {
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.1) !important;
}

.badge {
    font-size: 0.75em;
}

.alert-sm {
    padding: 0.25rem 0.5rem;
    margin-bottom: 0;
    font-size: 0.875rem;
}

.progress-sm {
    height: 0.5rem;
}

.card-header .btn-link {
    color: inherit;
    text-decoration: none;
}

.card-header .btn-link:hover {
    color: inherit;
    text-decoration: none;
}

.test-category-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 1px solid #dee2e6;
}

.test-duration-badge {
    min-width: 70px;
    text-align: center;
}

.test-status-column {
    min-width: 80px;
}

/* Animation for test result rows */
.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Category cards hover effect */
.card:hover {
    transform: translateY(-2px);
    transition: all 0.2s ease;
}

/* Progress bar improvements */
.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}
</style>
@endsection

@section('scripts')
<script>
let deleteTestId = null;
const deleteBaseUrl = '{{ url("admin/security/testing/delete") }}';

function getDeleteUrl(id) {
    return `${deleteBaseUrl}/${id}`;
}

function deleteTest(testId) {
    deleteTestId = testId;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

document.getElementById('confirmDelete').addEventListener('click', function() {
    if (deleteTestId) {
        fetch(getDeleteUrl(deleteTestId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '{{ route("security.testing.history") }}';
            } else {
                alert('Error deleting test result: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting test result');
        });
    }
});
</script>
@endsection
