@extends('layouts.app')

@section('title', 'Security Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Security Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-0 text-danger">
                                <i class="fas fa-shield-alt me-2"></i>
                                Security Dashboard
                            </h1>
                            <p class="text-muted mb-0">Real-time threat monitoring and security analytics</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt me-1"></i> Refresh
                            </button>
                            <button class="btn btn-outline-success" onclick="exportLogs()">
                                <i class="fas fa-download me-1"></i> Export Logs
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-cog me-1"></i> Settings
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="showSecurityConfig()">Security Config</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="showIPManagement()">IP Management</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="showAlertSettings()">Alert Settings</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Metrics Overview -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Today's Threats
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="today-threats">
                                {{ $securityMetrics['today']['total_events'] ?? 0 }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Failed Logins
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="failed-logins">
                                {{ $securityMetrics['today']['failed_logins'] ?? 0 }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-lock fa-2x text-gray-300"></i>
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
                                Blocked IPs
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="blocked-ips">
                                {{ count($blockedIPs) }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-ban fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                System Health
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="system-health">
                                {{ $systemHealth['security_services']['status'] ?? 'Unknown' }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-heartbeat fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Analytics -->
    <div class="row mb-4">
        <!-- Threat Timeline Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Threat Timeline</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                            <a class="dropdown-item" href="#" onclick="updateChart('1d')">Last 24 Hours</a>
                            <a class="dropdown-item" href="#" onclick="updateChart('7d')">Last 7 Days</a>
                            <a class="dropdown-item" href="#" onclick="updateChart('30d')">Last 30 Days</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="threatTimelineChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Threat Distribution -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Threat Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="threatDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Tables -->
    <div class="row">
        <!-- Recent Threats Table -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Threats</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="recentThreatsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>IP Address</th>
                                    <th>Severity</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="recentThreatsBody">
                                @foreach($recentThreats as $threat)
                                <tr>
                                    <td>{{ $threat['timestamp'] ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $threat['type'] === 'sql_injection' ? 'danger' : 'warning' }}">
                                            {{ $threat['type'] ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td>{{ $threat['ip'] ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $threat['severity'] === 'high' ? 'danger' : ($threat['severity'] === 'medium' ? 'warning' : 'info') }}">
                                            {{ ucfirst($threat['severity'] ?? 'low') }}
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" onclick="blockIP('{{ $threat['ip'] ?? '' }}')">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Blocked IPs Table -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Blocked IP Addresses</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="blockedIPsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Blocked At</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="blockedIPsBody">
                                @foreach($blockedIPs as $blocked)
                                <tr>
                                    <td>{{ $blocked['ip'] }}</td>
                                    <td>{{ $blocked['blocked_at'] }}</td>
                                    <td>{{ $blocked['reason'] }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-success" onclick="unblockIP('{{ $blocked['ip'] }}')">
                                            <i class="fas fa-unlock"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health Status -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Health Status</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($systemHealth as $component => $status)
                        <div class="col-md-4 mb-3">
                            <div class="card border-left-{{ $status['status'] === 'healthy' ? 'success' : ($status['status'] === 'warning' ? 'warning' : 'danger') }} shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                {{ ucfirst(str_replace('_', ' ', $component)) }}
                                            </div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                {{ ucfirst($status['status']) }}
                                            </div>
                                            <div class="text-xs text-muted">
                                                {{ $status['message'] }}
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-{{ $status['status'] === 'healthy' ? 'check-circle' : ($status['status'] === 'warning' ? 'exclamation-triangle' : 'times-circle') }} fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- IP Management Modal -->
<div class="modal fade" id="ipManagementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">IP Management</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Block IP Address</h6>
                        <form id="blockIPForm">
                            <div class="mb-3">
                                <label for="blockIP" class="form-label">IP Address</label>
                                <input type="text" class="form-control" id="blockIP" placeholder="192.168.1.1" required>
                            </div>
                            <div class="mb-3">
                                <label for="blockDuration" class="form-label">Duration (minutes)</label>
                                <select class="form-select" id="blockDuration">
                                    <option value="60">1 Hour</option>
                                    <option value="240">4 Hours</option>
                                    <option value="720">12 Hours</option>
                                    <option value="1440">24 Hours</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger">Block IP</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h6>Current Blocked IPs</h6>
                        <div id="currentBlockedIPs">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Security Configuration Modal -->
<div class="modal fade" id="securityConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Security Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="securityConfigContent">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let threatTimelineChart;
let threatDistributionChart;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    startRealTimeUpdates();
});

// Initialize charts
function initializeCharts() {
    // Threat Timeline Chart
    const timelineCtx = document.getElementById('threatTimelineChart').getContext('2d');
    threatTimelineChart = new Chart(timelineCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Threats',
                data: [],
                borderColor: 'rgb(220, 53, 69)',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Threat Distribution Chart
    const distributionCtx = document.getElementById('threatDistributionChart').getContext('2d');
    threatDistributionChart = new Chart(distributionCtx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    '#e74a3b',
                    '#f6c23e',
                    '#1cc88a',
                    '#36b9cc',
                    '#858796'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Load initial data
    loadAnalytics('7d');
}

// Load analytics data
function loadAnalytics(period) {
    fetch(`/admin/security/analytics?period=${period}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateThreatTimeline(data.data.threat_timeline);
                updateThreatDistribution(data.data.threat_distribution);
            }
        })
        .catch(error => console.error('Error loading analytics:', error));
}

// Update threat timeline chart
function updateThreatTimeline(timelineData) {
    const labels = timelineData.map(item => item.date);
    const data = timelineData.map(item => item.total_events);
    
    threatTimelineChart.data.labels = labels;
    threatTimelineChart.data.datasets[0].data = data;
    threatTimelineChart.update();
}

// Update threat distribution chart
function updateThreatDistribution(distributionData) {
    const labels = Object.keys(distributionData);
    const data = Object.values(distributionData);
    
    threatDistributionChart.data.labels = labels;
    threatDistributionChart.data.datasets[0].data = data;
    threatDistributionChart.update();
}

// Start real-time updates
function startRealTimeUpdates() {
    // Update metrics every 30 seconds
    setInterval(refreshMetrics, 30000);
    
    // Update charts every 5 minutes
    setInterval(() => loadAnalytics('7d'), 300000);
}

// Refresh metrics
function refreshMetrics() {
    fetch('/admin/security/metrics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateMetricsDisplay(data.data.metrics);
            }
        })
        .catch(error => console.error('Error refreshing metrics:', error));
}

// Update metrics display
function updateMetricsDisplay(metrics) {
    document.getElementById('today-threats').textContent = metrics.today.total_events;
    document.getElementById('failed-logins').textContent = metrics.today.failed_logins;
    // Update other metrics as needed
}

// Refresh entire dashboard
function refreshDashboard() {
    location.reload();
}

// Update chart period
function updateChart(period) {
    loadAnalytics(period);
}

// Block IP address
function blockIP(ip) {
    if (confirm(`Are you sure you want to block IP address ${ip}?`)) {
        fetch('/admin/security/block-ip', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                ip: ip,
                duration: 60
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error blocking IP:', error);
            alert('Error blocking IP address');
        });
    }
}

// Unblock IP address
function unblockIP(ip) {
    if (confirm(`Are you sure you want to unblock IP address ${ip}?`)) {
        fetch('/admin/security/unblock-ip', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                ip: ip
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error unblocking IP:', error);
            alert('Error unblocking IP address');
        });
    }
}

// Show IP management modal
function showIPManagement() {
    const modal = new bootstrap.Modal(document.getElementById('ipManagementModal'));
    modal.show();
}

// Show security configuration modal
function showSecurityConfig() {
    fetch('/admin/security/config')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySecurityConfig(data.data);
                const modal = new bootstrap.Modal(document.getElementById('securityConfigModal'));
                modal.show();
            }
        })
        .catch(error => console.error('Error loading security config:', error));
}

// Display security configuration
function displaySecurityConfig(config) {
    const content = document.getElementById('securityConfigContent');
    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Security Features</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        Encryption Enabled
                        <span class="badge bg-${config.encryption_enabled ? 'success' : 'danger'}">
                            ${config.encryption_enabled ? 'Yes' : 'No'}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        HTTPS Enforced
                        <span class="badge bg-${config.https_enforced ? 'success' : 'danger'}">
                            ${config.https_enforced ? 'Yes' : 'No'}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Rate Limiting
                        <span class="badge bg-${config.rate_limiting_enabled ? 'success' : 'danger'}">
                            ${config.rate_limiting_enabled ? 'Yes' : 'No'}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Threat Detection
                        <span class="badge bg-${config.threat_detection_enabled ? 'success' : 'danger'}">
                            ${config.threat_detection_enabled ? 'Yes' : 'No'}
                        </span>
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Additional Security</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        File Upload Security
                        <span class="badge bg-${config.file_upload_security ? 'success' : 'danger'}">
                            ${config.file_upload_security ? 'Yes' : 'No'}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Debug Access Restricted
                        <span class="badge bg-${config.debug_access_restricted ? 'success' : 'danger'}">
                            ${config.debug_access_restricted ? 'Yes' : 'No'}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Audit Logging
                        <span class="badge bg-${config.audit_logging_enabled ? 'success' : 'danger'}">
                            ${config.audit_logging_enabled ? 'Yes' : 'No'}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        Security Headers
                        <span class="badge bg-${config.security_headers_enabled ? 'success' : 'danger'}">
                            ${config.security_headers_enabled ? 'Yes' : 'No'}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    `;
}

// Export logs
function exportLogs() {
    const startDate = prompt('Enter start date (YYYY-MM-DD):', new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
    const endDate = prompt('Enter end date (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
    
    if (startDate && endDate) {
        fetch(`/admin/security/export-logs?start_date=${startDate}&end_date=${endDate}&log_type=all`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create and download file
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `security-logs-${startDate}-to-${endDate}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('Error exporting logs');
                }
            })
            .catch(error => {
                console.error('Error exporting logs:', error);
                alert('Error exporting logs');
            });
    }
}

// Show alert settings
function showAlertSettings() {
    alert('Alert settings functionality will be implemented in the next version.');
}
</script>
@endpush

@push('styles')
<style>
.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.chart-area {
    position: relative;
    height: 10rem;
    width: 100%;
}
.chart-pie {
    position: relative;
    height: 15rem;
    width: 100%;
}
</style>
@endpush
