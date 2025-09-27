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
                        <button type="button" class="btn btn-secondary" onclick="testConnection()">
                            <i class="fas fa-plug"></i> Test Connection
                        </button>
                        <form method="POST" action="{{ route('admin.seeder-management.run-migrations') }}" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-warning" onclick="return confirm('This will run database migrations. Continue?')">
                                <i class="fas fa-database"></i> Run Migrations
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.seeder-management.run-all-core') }}" style="display: inline;">
                            @csrf
                            <input type="hidden" name="clear_existing" value="0">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to run all core seeders? This may take several minutes.')">
                                <i class="fas fa-play"></i> Run All Core Seeders
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Success/Error Messages -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif
                    
                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif
                    
                    <!-- Debug Info -->
                    <div class="alert alert-info" id="debugInfo" style="display: none;">
                        <h6><i class="fas fa-bug"></i> Debug Information</h6>
                        <div id="debugContent"></div>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Instructions</h6>
                        <ol>
                            <li><strong>First:</strong> Click "Run Migrations" to create missing database tables</li>
                            <li><strong>Then:</strong> Click "Run All Core Seeders" to populate the tables with data</li>
                            <li><strong>Or:</strong> Use individual seeder buttons to run specific seeders</li>
                        </ol>
                    </div>
                    
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
                                                        @elseif($seeder['status'] === 'migration_pending')
                                                            <span class="badge badge-info">Migration Pending</span>
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
                                                            <form method="POST" action="{{ route('admin.seeder-management.run') }}" style="display: inline;">
                                                                @csrf
                                                                <input type="hidden" name="seeder_class" value="{{ $seeder['class'] }}">
                                                                <input type="hidden" name="clear_existing" value="0">
                                                                <button type="submit" class="btn btn-sm btn-success" title="Run Seeder">
                                                                    <i class="fas fa-play"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="{{ route('admin.seeder-management.run') }}" style="display: inline;">
                                                                @csrf
                                                                <input type="hidden" name="seeder_class" value="{{ $seeder['class'] }}">
                                                                <input type="hidden" name="clear_existing" value="1">
                                                                <button type="submit" class="btn btn-sm btn-warning" title="Clear & Run">
                                                                    <i class="fas fa-redo"></i>
                                                                </button>
                                                            </form>
                                                            <a href="{{ route('admin.seeder-management.status') }}?seeder_class={{ $seeder['class'] }}" 
                                                               class="btn btn-sm btn-info" title="View Status" target="_blank">
                                                                <i class="fas fa-info-circle"></i>
                                                            </a>
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

// Debug function to log errors
function logError(error, context = '') {
    console.error('Seeder Management Error' + (context ? ' (' + context + ')' : '') + ':', error);
    console.error('Stack trace:', error.stack);
}

// Show debug information
function showDebugInfo(info) {
    const debugInfo = document.getElementById('debugInfo');
    const debugContent = document.getElementById('debugContent');
    
    debugContent.innerHTML = `
        <pre style="margin: 0; font-size: 12px;">${JSON.stringify(info, null, 2)}</pre>
    `;
    debugInfo.style.display = 'block';
}

// Hide debug information
function hideDebugInfo() {
    document.getElementById('debugInfo').style.display = 'none';
}

// Test connection function
function testConnection() {
    console.log('Testing connection...');
    
    const debugInfo = {
        csrf_token: csrfToken,
        current_url: window.location.href,
        user_agent: navigator.userAgent,
        timestamp: new Date().toISOString()
    };
    
    showDebugInfo(debugInfo);
    
    fetch('{{ route("admin.seeder-management.status") }}', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Test response status:', response.status);
        
        const responseInfo = {
            ...debugInfo,
            response_status: response.status,
            response_ok: response.ok,
            response_headers: Object.fromEntries(response.headers.entries())
        };
        
        showDebugInfo(responseInfo);
        
        if (response.ok) {
            showAlert('success', 'Connection successful! Server is responding.');
        } else {
            showAlert('error', `Connection failed: HTTP ${response.status}`);
        }
        return response.text();
    })
    .then(data => {
        console.log('Test response data:', data);
        const finalInfo = {
            ...debugInfo,
            response_data: data.substring(0, 500) + (data.length > 500 ? '...' : '')
        };
        showDebugInfo(finalInfo);
    })
    .catch(error => {
        logError(error, 'testConnection');
        
        const errorInfo = {
            ...debugInfo,
            error: {
                message: error.message,
                name: error.name,
                stack: error.stack
            }
        };
        showDebugInfo(errorInfo);
        
        showAlert('error', 'Connection failed: ' + error.message);
    });
}

// Run single seeder
function runSeeder(seederClass, clearExisting = false) {
    console.log('Running seeder:', seederClass, 'Clear existing:', clearExisting);
    
    if (!confirm(`Are you sure you want to run ${seederClass}?${clearExisting ? ' This will clear existing data first.' : ''}`)) {
        return;
    }

    showProgressModal();
    updateProgress(0, `Running ${seederClass}...`);

    // Use FormData for better compatibility
    const formData = new FormData();
    formData.append('seeder_class', seederClass);
    formData.append('clear_existing', clearExisting ? '1' : '0');
    formData.append('_token', csrfToken);

    fetch('{{ route("admin.seeder-management.run") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Seeder response:', data);
        updateProgress(100, data.message || 'Completed');
        setTimeout(() => {
            hideProgressModal();
            showAlert(data.success ? 'success' : 'error', data.message || 'Operation completed');
            if (data.success) {
                setTimeout(() => location.reload(), 1000);
            }
        }, 2000);
    })
    .catch(error => {
        logError(error, 'runSeeder');
        updateProgress(100, 'Error occurred');
        setTimeout(() => {
            hideProgressModal();
            showAlert('error', 'An error occurred: ' + error.message);
        }, 2000);
    });
}

// Run all core seeders
function runAllCoreSeeders() {
    console.log('Running all core seeders');
    
    if (!confirm('Are you sure you want to run all core seeders? This may take several minutes.')) {
        return;
    }

    showProgressModal();
    updateProgress(0, 'Running all core seeders...');

    // Use FormData for better compatibility
    const formData = new FormData();
    formData.append('clear_existing', '0');
    formData.append('_token', csrfToken);

    fetch('{{ route("admin.seeder-management.run-all-core") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(response => {
        console.log('Core seeders response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Core seeders response:', data);
        updateProgress(100, data.message || 'All core seeders completed');
        
        // Display results
        let resultsHtml = '<h6>Results:</h6><ul>';
        if (data.results && Array.isArray(data.results)) {
            data.results.forEach(result => {
                const icon = result.success ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>';
                resultsHtml += `<li>${icon} ${result.seeder}: ${result.message}</li>`;
            });
        } else {
            resultsHtml += '<li>No detailed results available</li>';
        }
        resultsHtml += '</ul>';
        
        document.getElementById('progressResults').innerHTML = resultsHtml;
        
        setTimeout(() => {
            hideProgressModal();
            showAlert(data.success ? 'success' : 'warning', data.message || 'Core seeders completed');
            if (data.success_count > 0) {
                setTimeout(() => location.reload(), 1000);
            }
        }, 3000);
    })
    .catch(error => {
        logError(error, 'runAllCoreSeeders');
        updateProgress(100, 'Error occurred');
        setTimeout(() => {
            hideProgressModal();
            showAlert('error', 'An error occurred: ' + error.message);
        }, 2000);
    });
}

// Get seeder status
function getSeederStatus(seederClass) {
    console.log('Getting status for seeder:', seederClass);
    
    $('#statusModal').modal('show');
    
    // Show loading state
    document.getElementById('statusContent').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p>Loading seeder status...</p>
        </div>
    `;
    
    // Build URL with query parameters
    const url = new URL('{{ route("admin.seeder-management.status") }}', window.location.origin);
    url.searchParams.append('seeder_class', seederClass);
    
    fetch(url.toString(), {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Status response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('Status response:', data);
        
        let html = `
            <h6>${data.seeder || seederClass}</h6>
            <p><strong>Total Records:</strong> ${data.total_records || 0}</p>
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
        
        if (data.tables && typeof data.tables === 'object') {
            for (const [table, info] of Object.entries(data.tables)) {
                html += `
                    <tr>
                        <td>${table}</td>
                        <td>${info.exists ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'}</td>
                        <td>${info.count || 0}</td>
                        <td>${info.last_updated || 'N/A'}</td>
                    </tr>
                `;
            }
        } else {
            html += '<tr><td colspan="4" class="text-center">No table information available</td></tr>';
        }
        
        html += '</tbody></table>';
        document.getElementById('statusContent').innerHTML = html;
    })
    .catch(error => {
        logError(error, 'getSeederStatus');
        document.getElementById('statusContent').innerHTML = `
            <div class="alert alert-danger">
                <h6>Error loading status</h6>
                <p>${error.message}</p>
                <small>Check browser console for more details.</small>
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
