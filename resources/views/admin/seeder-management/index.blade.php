@extends('layouts.app')

@section('title', 'Seeder Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database"></i> Seeder Management
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" onclick="runAllCoreSeeders()">
                            <i class="fas fa-play"></i> Run All Core Seeders
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- System Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5>System Status</h5>
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-info"><i class="fas fa-building"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Tenants</span>
                                            <span class="info-box-number">{{ $systemStatus['total_tenants'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-success"><i class="fas fa-users"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Users</span>
                                            <span class="info-box-number">{{ $systemStatus['total_users'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-warning"><i class="fas fa-box"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Packages</span>
                                            <span class="info-box-number">{{ $systemStatus['total_packages'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-primary"><i class="fas fa-coins"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Currencies</span>
                                            <span class="info-box-number">{{ $systemStatus['total_currencies'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-secondary"><i class="fas fa-user-tag"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Roles</span>
                                            <span class="info-box-number">{{ $systemStatus['total_roles'] }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="info-box">
                                        <span class="info-box-icon bg-dark"><i class="fas fa-hdd"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">DB Size</span>
                                            <span class="info-box-number">{{ $systemStatus['database_size'] }} MB</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seeder Categories -->
                    @php
                        $categories = collect($availableSeeders)->groupBy('category');
                    @endphp

                    @foreach($categories as $category => $seeders)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-folder"></i> {{ $category }}
                                    <span class="badge badge-info">{{ $seeders->count() }} seeders</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Data Count</th>
                                                <th>Priority</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($seeders->sortBy('priority') as $seeder)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $seeder['name'] }}</strong>
                                                        <br>
                                                        <small class="text-muted">{{ $seeder['class'] }}</small>
                                                    </td>
                                                    <td>{{ $seeder['description'] }}</td>
                                                    <td>
                                                        @if($seeder['status'] === 'populated')
                                                            <span class="badge badge-success">Populated</span>
                                                        @elseif($seeder['status'] === 'empty')
                                                            <span class="badge badge-warning">Empty</span>
                                                        @else
                                                            <span class="badge badge-danger">Table Missing</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($seeder['has_data'])
                                                            <span class="badge badge-info">{{ $seeder['data_count'] }} records</span>
                                                        @else
                                                            <span class="badge badge-secondary">0 records</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-primary">{{ $seeder['priority'] }}</span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    onclick="runSeeder('{{ $seeder['class'] }}', false)"
                                                                    title="Run Seeder">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-warning" 
                                                                    onclick="runSeeder('{{ $seeder['class'] }}', true)"
                                                                    title="Clear & Run">
                                                                <i class="fas fa-redo"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-info" 
                                                                    onclick="getSeederStatus('{{ $seeder['class'] }}')"
                                                                    title="View Status">
                                                                <i class="fas fa-info-circle"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Seeder Status</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="statusContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div class="modal fade" id="progressModal" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Running Seeders</h5>
            </div>
            <div class="modal-body">
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
                <div id="progressText">Initializing...</div>
                <div id="progressResults" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// CSRF Token
const csrfToken = '{{ csrf_token() }}';

// Run single seeder
function runSeeder(seederClass, clearExisting = false) {
    if (!confirm(`Are you sure you want to run ${seederClass}?${clearExisting ? ' This will clear existing data first.' : ''}`)) {
        return;
    }

    showProgressModal();
    updateProgress(0, `Running ${seederClass}...`);

    fetch('{{ route("admin.seeder-management.run") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            seeder_class: seederClass,
            clear_existing: clearExisting
        })
    })
    .then(response => response.json())
    .then(data => {
        updateProgress(100, data.message);
        setTimeout(() => {
            hideProgressModal();
            showAlert(data.success ? 'success' : 'error', data.message);
            if (data.success) {
                location.reload();
            }
        }, 2000);
    })
    .catch(error => {
        updateProgress(100, 'Error: ' + error.message);
        setTimeout(() => {
            hideProgressModal();
            showAlert('error', 'An error occurred: ' + error.message);
        }, 2000);
    });
}

// Run all core seeders
function runAllCoreSeeders() {
    if (!confirm('Are you sure you want to run all core seeders? This may take several minutes.')) {
        return;
    }

    showProgressModal();
    updateProgress(0, 'Running all core seeders...');

    fetch('{{ route("admin.seeder-management.run-all-core") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            clear_existing: false
        })
    })
    .then(response => response.json())
    .then(data => {
        updateProgress(100, data.message);
        
        // Display results
        let resultsHtml = '<h6>Results:</h6><ul>';
        data.results.forEach(result => {
            const icon = result.success ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>';
            resultsHtml += `<li>${icon} ${result.seeder}: ${result.message}</li>`;
        });
        resultsHtml += '</ul>';
        
        document.getElementById('progressResults').innerHTML = resultsHtml;
        
        setTimeout(() => {
            hideProgressModal();
            showAlert(data.success ? 'success' : 'warning', data.message);
            if (data.success_count > 0) {
                location.reload();
            }
        }, 3000);
    })
    .catch(error => {
        updateProgress(100, 'Error: ' + error.message);
        setTimeout(() => {
            hideProgressModal();
            showAlert('error', 'An error occurred: ' + error.message);
        }, 2000);
    });
}

// Get seeder status
function getSeederStatus(seederClass) {
    $('#statusModal').modal('show');
    
    fetch('{{ route("admin.seeder-management.status") }}', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: new URLSearchParams({
            seeder_class: seederClass
        })
    })
    .then(response => response.json())
    .then(data => {
        let html = `
            <h6>${data.seeder}</h6>
            <p><strong>Total Records:</strong> ${data.total_records}</p>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Exists</th>
                        <th>Count</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        for (const [table, info] of Object.entries(data.tables)) {
            html += `
                <tr>
                    <td>${table}</td>
                    <td>${info.exists ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'}</td>
                    <td>${info.count}</td>
                    <td>${info.last_updated || 'N/A'}</td>
                </tr>
            `;
        }
        
        html += '</tbody></table>';
        document.getElementById('statusContent').innerHTML = html;
    })
    .catch(error => {
        document.getElementById('statusContent').innerHTML = `
            <div class="alert alert-danger">
                Error loading status: ${error.message}
            </div>
        `;
    });
}

// Progress modal functions
function showProgressModal() {
    $('#progressModal').modal('show');
    document.getElementById('progressResults').innerHTML = '';
}

function hideProgressModal() {
    $('#progressModal').modal('hide');
}

function updateProgress(percent, text) {
    document.querySelector('.progress-bar').style.width = percent + '%';
    document.getElementById('progressText').textContent = text;
}

// Show alert
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'warning' ? 'alert-warning' : 'alert-danger';
    
    const alert = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    // Insert at top of card body
    const cardBody = document.querySelector('.card-body');
    cardBody.insertAdjacentHTML('afterbegin', alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alertElement = cardBody.querySelector('.alert');
        if (alertElement) {
            alertElement.remove();
        }
    }, 5000);
}
</script>
@endpush
