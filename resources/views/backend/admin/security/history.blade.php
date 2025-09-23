@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-history me-2"></i>{{ _lang('Security Test History') }}
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.security.dashboard') }}">{{ _lang('Security') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('security.testing') }}">{{ _lang('Testing') }}</a></li>
                <li class="breadcrumb-item active">{{ _lang('History') }}</li>
            </ol>
        </nav>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter me-2"></i>{{ _lang('Filter Test History') }}
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('security.testing.history') }}" class="row">
                        <div class="col-md-3">
                            <label for="test_type" class="form-label">{{ _lang('Test Type') }}</label>
                            <select name="test_type" id="test_type" class="form-select">
                                <option value="">{{ _lang('All Types') }}</option>
                                <option value="all" {{ request('test_type') == 'all' ? 'selected' : '' }}>{{ _lang('All Tests') }}</option>
                                <option value="security" {{ request('test_type') == 'security' ? 'selected' : '' }}>{{ _lang('Security Tests') }}</option>
                                <option value="financial" {{ request('test_type') == 'financial' ? 'selected' : '' }}>{{ _lang('Financial Tests') }}</option>
                                <option value="calculations" {{ request('test_type') == 'calculations' ? 'selected' : '' }}>{{ _lang('Calculation Tests') }}</option>
                                <option value="modules" {{ request('test_type') == 'modules' ? 'selected' : '' }}>{{ _lang('Module Tests') }}</option>
                                <option value="performance" {{ request('test_type') == 'performance' ? 'selected' : '' }}>{{ _lang('Performance Tests') }}</option>
                                <option value="compliance" {{ request('test_type') == 'compliance' ? 'selected' : '' }}>{{ _lang('Compliance Tests') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="per_page" class="form-label">{{ _lang('Results Per Page') }}</label>
                            <select name="per_page" id="per_page" class="form-select">
                                <option value="10" {{ request('per_page') == '10' ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == '25' ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i>{{ _lang('Filter') }}
                            </button>
                            <a href="{{ route('security.testing.history') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>{{ _lang('Clear') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Test History Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list me-2"></i>{{ _lang('Test History') }}
                    </h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow">
                            <a class="dropdown-item" href="{{ route('security.testing') }}">
                                <i class="fas fa-arrow-left fa-sm fa-fw mr-2 text-gray-400"></i>
                                {{ _lang('Back to Testing') }}
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('security.testing.history') }}">
                                <i class="fas fa-sync fa-sm fa-fw mr-2 text-gray-400"></i>
                                {{ _lang('Refresh') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($testHistory->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('ID') }}</th>
                                        <th>{{ _lang('Test Type') }}</th>
                                        <th>{{ _lang('Results') }}</th>
                                        <th>{{ _lang('Success Rate') }}</th>
                                        <th>{{ _lang('Duration') }}</th>
                                        <th>{{ _lang('Date') }}</th>
                                        <th>{{ _lang('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($testHistory as $test)
                                        <tr>
                                            <td><span class="badge badge-info">#{{ $test->id }}</span></td>
                                            <td>
                                                <span class="badge badge-{{ $test->test_type == 'all' ? 'primary' : ($test->test_type == 'security' ? 'info' : ($test->test_type == 'financial' ? 'success' : 'secondary')) }}">
                                                    {{ ucfirst($test->test_type) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-success">
                                                        <i class="fas fa-check-circle"></i> {{ $test->passed_tests }}
                                                    </span>
                                                    <span class="text-danger">
                                                        <i class="fas fa-times-circle"></i> {{ $test->failed_tests }}
                                                    </span>
                                                    <span class="text-info">
                                                        <i class="fas fa-list"></i> {{ $test->total_tests }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-{{ $test->success_rate >= 80 ? 'success' : ($test->success_rate >= 60 ? 'warning' : 'danger') }}" 
                                                         style="width: {{ $test->success_rate }}%">
                                                        {{ $test->success_rate }}%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-light">{{ $test->duration_seconds }}s</span>
                                            </td>
                                            <td>
                                                <small>{{ $test->test_completed_at ? $test->test_completed_at->format('M j, Y H:i') : 'N/A' }}</small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('security.testing.detail', $test->id) }}" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="{{ _lang('View Details') }}">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteTest({{ $test->id }})"
                                                            title="{{ _lang('Delete') }}">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted">
                                    {{ _lang('Showing') }} {{ $testHistory->firstItem() }} {{ _lang('to') }} {{ $testHistory->lastItem() }} 
                                    {{ _lang('of') }} {{ $testHistory->total() }} {{ _lang('results') }}
                                </small>
                            </div>
                            <div>
                                {{ $testHistory->appends(request()->query())->links() }}
                            </div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No Test History Found') }}</h5>
                            <p class="text-muted">{{ _lang('Run some tests first to see history here.') }}</p>
                            <a href="{{ route('security.testing') }}" class="btn btn-primary">
                                <i class="fas fa-play me-2"></i>{{ _lang('Run Tests') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
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
                location.reload();
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
