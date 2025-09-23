@extends('layouts.app')

@section('title', _lang('Security Testing Dashboard'))

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.security.dashboard') }}">{{ _lang('Security') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Testing') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">
                    <i class="fas fa-vial me-2"></i>{{ _lang('Security Testing Dashboard') }}
                </h4>
            </div>
        </div>
    </div>

    <!-- Test Controls -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-play-circle me-2"></i>{{ _lang('Security Test Suite') }}
                    </h5>
                    <div class="btn-group" role="group">
                        <a href="{{ route('security.testing.results') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-sync-alt me-1"></i>{{ _lang('Refresh') }}
                        </a>
                        <a href="{{ route('security.testing.standards') }}" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-info-circle me-1"></i>{{ _lang('Standards') }}
                        </a>
                            <a href="{{ route('security.testing.history') }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-history me-1"></i>{{ _lang('History') }}
                            </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Flash Messages -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <!-- Display Test Results if Available -->
                    @if(session('test_results'))
                        @php $results = session('test_results'); @endphp
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-check-circle me-2"></i>Test Results Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-success">{{ $results['overall']['passed'] ?? 0 }}</h4>
                                            <small class="text-muted">Passed</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-danger">{{ $results['overall']['failed'] ?? 0 }}</h4>
                                            <small class="text-muted">Failed</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-info">{{ $results['overall']['total'] ?? 0 }}</h4>
                                            <small class="text-muted">Total</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-primary">{{ isset($results['overall']['total']) && $results['overall']['total'] > 0 ? round(($results['overall']['passed'] / $results['overall']['total']) * 100, 1) : 0 }}%</h4>
                                            <small class="text-muted">Success Rate</small>
                                        </div>
                                    </div>
                                </div>
                                @if(isset($results['test_type']))
                                    <div class="mt-2">
                                        <small class="text-muted">Test Type: <strong>{{ ucfirst($results['test_type']) }}</strong></small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    <!-- Primary Test Button -->
                    <div class="row mb-3">
                        <div class="col-12 text-center">
                            <form action="{{ route('security.testing.run') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="test_type" value="all">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-play me-2"></i>{{ _lang('Run All Tests') }}
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    
                    <!-- Individual Test Categories -->
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <div class="test-category-card">
                                <form action="{{ route('security.testing.run') }}" method="POST" class="w-100">
                                    @csrf
                                    <input type="hidden" name="test_type" value="security">
                                    <button type="submit" class="btn btn-info w-100 test-btn">
                                        <div class="test-btn-content">
                                            <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                            <div class="fw-bold">{{ _lang('Security Tests') }}</div>
                                            <small class="text-muted">{{ _lang('Encryption, Authentication, CSRF') }}</small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="test-category-card">
                                <form action="{{ route('security.testing.run') }}" method="POST" class="w-100">
                                    @csrf
                                    <input type="hidden" name="test_type" value="financial">
                                    <button type="submit" class="btn btn-success w-100 test-btn">
                                        <div class="test-btn-content">
                                            <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                            <div class="fw-bold">{{ _lang('Financial Tests') }}</div>
                                            <small class="text-muted">{{ _lang('Balance, Integrity, Audit') }}</small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="test-category-card">
                                <form action="{{ route('security.testing.run') }}" method="POST" class="w-100">
                                    @csrf
                                    <input type="hidden" name="test_type" value="calculations">
                                    <button type="submit" class="btn btn-warning w-100 test-btn">
                                        <div class="test-btn-content">
                                            <i class="fas fa-calculator fa-2x mb-2"></i>
                                            <div class="fw-bold">{{ _lang('Calculation Tests') }}</div>
                                            <small class="text-muted">{{ _lang('Interest, EMI, Currency') }}</small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="test-category-card">
                                <form action="{{ route('security.testing.run') }}" method="POST" class="w-100">
                                    @csrf
                                    <input type="hidden" name="test_type" value="modules">
                                    <button type="submit" class="btn btn-secondary w-100 test-btn">
                                        <div class="test-btn-content">
                                            <i class="fas fa-puzzle-piece fa-2x mb-2"></i>
                                            <div class="fw-bold">{{ _lang('Module Tests') }}</div>
                                            <small class="text-muted">{{ _lang('QR Code, VSLA, Loans') }}</small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="test-category-card">
                                <form action="{{ route('security.testing.run') }}" method="POST" class="w-100">
                                    @csrf
                                    <input type="hidden" name="test_type" value="performance">
                                    <button type="submit" class="btn btn-dark w-100 test-btn">
                                        <div class="test-btn-content">
                                            <i class="fas fa-tachometer-alt fa-2x mb-2"></i>
                                            <div class="fw-bold">{{ _lang('Performance Tests') }}</div>
                                            <small class="text-muted">{{ _lang('Speed, Memory, Cache') }}</small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="test-category-card">
                                <form action="{{ route('security.testing.run') }}" method="POST" class="w-100">
                                    @csrf
                                    <input type="hidden" name="test_type" value="compliance">
                                    <button type="submit" class="btn btn-purple w-100 test-btn">
                                        <div class="test-btn-content">
                                            <i class="fas fa-gavel fa-2x mb-2"></i>
                                            <div class="fw-bold">{{ _lang('Compliance Tests') }}</div>
                                            <small class="text-muted">{{ _lang('PCI DSS, Basel III, GDPR') }}</small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Progress -->
    <div class="row mb-4" id="testProgress" style="display: none;">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-cog fa-spin me-2"></i>{{ _lang('Running Security Tests') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="spinner-border text-primary me-3" role="status">
                            <span class="visually-hidden">{{ _lang('Loading') }}...</span>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-primary">{{ _lang('Running Tests') }}...</h6>
                            <p class="text-muted mb-0" id="testStatus">{{ _lang('Initializing test suite') }}</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <span id="testTimer">0s</span>
                            </small>
                        </div>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             role="progressbar" style="width: 0%" id="testProgressBar">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    <div class="mt-2 text-center">
                        <small class="text-muted">{{ _lang('Please wait while tests are running...') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Section Divider -->
    <div class="row" id="resultsDivider" style="display: none;">
        <div class="col-12">
            <hr class="my-4">
            <div class="text-center mb-4">
                <h4 class="text-muted">
                    <i class="fas fa-chart-line me-2"></i>{{ _lang('Test Results') }}
                </h4>
            </div>
        </div>
    </div>

    <!-- Test Results Summary -->
    <div class="row mb-4" id="testSummary" style="display: none;">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>{{ _lang('Test Results Summary') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-primary mb-1" id="totalTests">0</h3>
                                <p class="text-muted mb-0">{{ _lang('Total Tests') }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-success mb-1" id="passedTests">0</h3>
                                <p class="text-muted mb-0">{{ _lang('Passed') }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-danger mb-1" id="failedTests">0</h3>
                                <p class="text-muted mb-0">{{ _lang('Failed') }}</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-info mb-1" id="successRate">0%</h3>
                                <p class="text-muted mb-0">{{ _lang('Success Rate') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                {{ _lang('Test Duration') }}: <span id="testDuration">0s</span>
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                {{ _lang('Last Run') }}: <span id="lastRunTime">-</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Categories -->
    <div class="row mb-4" id="testCategoriesSection" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-check me-2"></i>{{ _lang('Detailed Test Results by Category') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row" id="testCategories">
                        <!-- Categories will be populated dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Banking Standards Modal -->
    <div class="modal fade" id="bankingStandardsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-gavel me-2"></i>{{ _lang('International Banking Standards') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row" id="bankingStandardsContent">
                        <!-- Standards will be populated dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ _lang('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Test History Modal -->
    <div class="modal fade" id="testHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>{{ _lang('Test History') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Filter Options -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="historyTestType" class="form-label">{{ _lang('Filter by Test Type') }}</label>
                            <select id="historyTestType" class="form-select" onchange="filterTestHistory()">
                                <option value="all">{{ _lang('All Tests') }}</option>
                                <option value="security">{{ _lang('Security Tests') }}</option>
                                <option value="financial">{{ _lang('Financial Tests') }}</option>
                                <option value="calculations">{{ _lang('Calculation Tests') }}</option>
                                <option value="modules">{{ _lang('Module Tests') }}</option>
                                <option value="performance">{{ _lang('Performance Tests') }}</option>
                                <option value="compliance">{{ _lang('Compliance Tests') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-primary" onclick="loadTestHistory()">
                                    <i class="fas fa-sync-alt me-1"></i>{{ _lang('Refresh') }}
                                </button>
                                <button type="button" class="btn btn-danger" onclick="clearAllHistory()" id="clearHistoryBtn" style="display: none;">
                                    <i class="fas fa-trash me-1"></i>{{ _lang('Clear All') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- History Content -->
                    <div id="testHistoryContent">
                        <!-- History will be populated dynamically -->
                    </div>

                    <!-- Pagination -->
                    <div id="historyPagination" class="mt-3">
                        <!-- Pagination will be populated dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ _lang('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Immediate test to verify script execution
console.log('=== SCRIPT LOADING TEST ===');
console.log('Script section is executing');

// Global variables
let testInterval;
let currentProgress = 0;
let testStartTime = null;
let originalButtonStates = new Map();

console.log('=== VARIABLES DECLARED ===');

// Run tests function implementation
function runTests(testType) {
    console.log('Starting tests:', testType);
    
    // Disable all test buttons and show loading state
    disableTestButtons(true, testType);
    
    // Immediately show progress to give user feedback
    const progressElement = document.getElementById('testProgress');
    if (!progressElement) {
        console.error('Progress element not found!');
        showAlert('error', 'UI elements not found. Please refresh the page.');
        disableTestButtons(false);
        return;
    }
    
    // Show progress and hide previous results
    progressElement.style.display = 'block';
    document.getElementById('resultsDivider').style.display = 'none';
    document.getElementById('testSummary').style.display = 'none';
    document.getElementById('testCategoriesSection').style.display = 'none';
    document.getElementById('testCategories').innerHTML = '';
    
    // Reset progress and start timer
    currentProgress = 0;
    testStartTime = Date.now();
    updateProgress();
    
    // Start progress animation
    testInterval = setInterval(updateProgress, 500);
    
    // Update status and header
    const statusElement = document.getElementById('testStatus');
    const headerElement = document.querySelector('#testProgress .card-header h5');
    
    if (statusElement) {
        statusElement.textContent = `Running ${getTestTypeLabel(testType)}...`;
    }
    if (headerElement) {
        headerElement.innerHTML = `<i class="fas fa-cog fa-spin me-2"></i>Running ${getTestTypeLabel(testType)}`;
    }
    
    // Show immediate feedback
    showAlert('info', `Starting ${getTestTypeLabel(testType)}...`);
    
    // Make API call
    fetch('{{ route("security.testing.run") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            test_type: testType
        })
    })
    .then(response => {
        if (response.status === 401 || response.status === 403) {
            throw new Error('Authentication required. Please login as super admin.');
        }
        if (response.status === 419) {
            throw new Error('Session expired. Please refresh the page and try again.');
        }
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        completeProgress();
        setTimeout(() => {
            document.getElementById('testProgress').style.display = 'none';
            disableTestButtons(false);
            
            if (data.success) {
                displayResults(data.results);
                showAlert('success', 'Tests completed successfully! Results saved to history.');
            } else {
                showAlert('error', data.message || 'Test execution failed');
            }
        }, 500); // Small delay to show 100% completion
    })
    .catch(error => {
        console.error('Test execution error:', error);
        clearInterval(testInterval);
        document.getElementById('testProgress').style.display = 'none';
        disableTestButtons(false);
        showAlert('error', 'Test execution failed: ' + error.message);
    });
}

// Make runTests immediately available
window.runTests = runTests;
console.log('=== runTests ASSIGNED ===', typeof window.runTests);

// testStartTime already declared above

// Update progress bar
function updateProgress() {
    currentProgress += Math.random() * 10 + 5; // More consistent progress increments
    if (currentProgress > 95) currentProgress = 95; // Leave room for completion
    
    const progressBar = document.getElementById('testProgressBar');
    const progressText = document.getElementById('progressText');
    const testTimer = document.getElementById('testTimer');
    
    if (progressBar) {
        progressBar.style.width = currentProgress + '%';
    }
    if (progressText) {
        progressText.textContent = Math.round(currentProgress) + '%';
    }
    
    // Update timer
    if (testStartTime && testTimer) {
        const elapsed = Math.floor((Date.now() - testStartTime) / 1000);
        testTimer.textContent = elapsed + 's';
    }
}

// Complete progress bar
function completeProgress() {
    currentProgress = 100;
    const progressBar = document.getElementById('testProgressBar');
    const progressText = document.getElementById('progressText');
    
    if (progressBar) {
        progressBar.style.width = '100%';
    }
    if (progressText) {
        progressText.textContent = '100%';
    }
    
    clearInterval(testInterval);
}

// originalButtonStates already declared above

// Disable/Enable test buttons
function disableTestButtons(disable, activeTestType = null) {
    const testButtons = document.querySelectorAll('.test-btn, .btn[onclick*="runTests"]');
    
    testButtons.forEach(button => {
        if (disable) {
            // Store original state
            if (!originalButtonStates.has(button)) {
                originalButtonStates.set(button, button.innerHTML);
            }
            
            button.disabled = true;
            button.classList.add('disabled');
            
            // Add loading spinner to the active button
            const onclick = button.getAttribute('onclick');
            if (onclick) {
                const testType = onclick.match(/runTests\('([^']+)'\)/);
                if (testType && testType[1] === activeTestType) {
                    const testTypeLabel = getTestTypeLabel(testType[1]);
                    button.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>Running ${testTypeLabel}...`;
                }
            } else if (button.innerHTML.includes('Run All Tests')) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Running All Tests...';
            }
        } else {
            button.disabled = false;
            button.classList.remove('disabled');
            
            // Restore original content
            if (originalButtonStates.has(button)) {
                button.innerHTML = originalButtonStates.get(button);
            }
        }
    });
}

// Get test type display label
function getTestTypeLabel(testType) {
    const labels = {
        'all': 'All Tests',
        'security': 'Security Tests',
        'financial': 'Financial Tests',
        'calculations': 'Calculation Tests',
        'modules': 'Module Tests',
        'performance': 'Performance Tests',
        'compliance': 'Compliance Tests'
    };
    return labels[testType] || testType;
}

// Display test results
function displayResults(results) {
    // Show divider and summary
    document.getElementById('resultsDivider').style.display = 'block';
    document.getElementById('testSummary').style.display = 'block';
    document.getElementById('totalTests').textContent = results.overall.total;
    document.getElementById('passedTests').textContent = results.overall.passed;
    document.getElementById('failedTests').textContent = results.overall.failed;
    
    const successRate = results.overall.total > 0 ? 
        Math.round((results.overall.passed / results.overall.total) * 100) : 0;
    document.getElementById('successRate').textContent = successRate + '%';
    
    document.getElementById('testDuration').textContent = results.duration + 's';
    document.getElementById('lastRunTime').textContent = new Date().toLocaleString();
    
    // Display categories
    displayCategories(results.categories);
    document.getElementById('testCategoriesSection').style.display = 'block';
    
    // Show appropriate alert
    if (successRate >= 90) {
        showAlert('success', 'Excellent! All tests passed successfully.');
    } else if (successRate >= 75) {
        showAlert('warning', 'Good! Minor issues detected. Review failed tests.');
    } else {
        showAlert('error', 'Warning! Several tests failed. System needs attention.');
    }
}

// Display test categories
function displayCategories(categories, containerId = 'testCategories') {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    
    Object.keys(categories).forEach(categoryName => {
        const category = categories[categoryName];
        const successRate = category.total > 0 ? 
            Math.round((category.passed / category.total) * 100) : 0;
        
        const statusClass = successRate >= 90 ? 'success' : successRate >= 75 ? 'warning' : 'danger';
        
        const cardHtml = `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 test-result-card ${statusClass}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas ${getCategoryIcon(categoryName)} me-2"></i>${categoryName}
                        </h6>
                        <span class="badge ${getBadgeClass(successRate)}">${successRate}%</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="text-success">
                                    <i class="fas fa-check-circle fa-lg mb-1"></i>
                                    <h5 class="mb-1">${category.passed}</h5>
                                    <small class="text-muted">{{ _lang('Passed') }}</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-danger">
                                    <i class="fas fa-times-circle fa-lg mb-1"></i>
                                    <h5 class="mb-1">${category.failed}</h5>
                                    <small class="text-muted">{{ _lang('Failed') }}</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-info">
                                    <i class="fas fa-list-alt fa-lg mb-1"></i>
                                    <h5 class="mb-1">${category.total}</h5>
                                    <small class="text-muted">{{ _lang('Total') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar ${getProgressBarClass(successRate)}" 
                                 style="width: ${successRate}%">${successRate}%</div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary w-100" 
                                onclick="showCategoryDetails('${categoryName}', ${JSON.stringify(category.tests).replace(/"/g, '&quot;')})">
                            <i class="fas fa-eye me-1"></i>{{ _lang('View Details') }}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', cardHtml);
    });
}

// Get badge class based on success rate
function getBadgeClass(successRate) {
    if (successRate >= 90) return 'bg-success';
    if (successRate >= 75) return 'bg-warning';
    return 'bg-danger';
}

// Get progress bar class based on success rate
function getProgressBarClass(successRate) {
    if (successRate >= 90) return 'bg-success';
    if (successRate >= 75) return 'bg-warning';
    return 'bg-danger';
}

// Get category icon
function getCategoryIcon(categoryName) {
    const icons = {
        'Security Tests': 'fa-shield-alt',
        'Financial System Tests': 'fa-dollar-sign',
        'Calculation Accuracy Tests': 'fa-calculator',
        'Module Functionality Tests': 'fa-puzzle-piece',
        'Performance Tests': 'fa-tachometer-alt',
        'Compliance Tests (Banking Standards)': 'fa-gavel'
    };
    return icons[categoryName] || 'fa-cog';
}

// Show category details
function showCategoryDetails(categoryName, tests) {
    let detailsHtml = `
        <div class="modal fade" id="categoryDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${categoryName} - {{ _lang('Test Details') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Test Name') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Duration') }}</th>
                                        <th>{{ _lang('Message') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
    `;
    
    tests.forEach(test => {
        const statusIcon = test.status === 'PASS' ? 'fas fa-check text-success' : 
                          test.status === 'FAIL' ? 'fas fa-times text-danger' : 
                          'fas fa-exclamation-triangle text-warning';
        
        detailsHtml += `
            <tr>
                <td>${test.name}</td>
                <td><i class="${statusIcon}"></i> ${test.status}</td>
                <td>${test.duration}ms</td>
                <td>${test.message}</td>
            </tr>
        `;
    });
    
    detailsHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ _lang('Close') }}</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('categoryDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', detailsHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('categoryDetailsModal'));
    modal.show();
}

// Show banking standards
function showBankingStandards() {
    console.log('Loading banking standards...');
    fetch('{{ route("security.testing.standards") }}')
        .then(response => {
            console.log('Standards response:', response);
            if (response.status === 401 || response.status === 403) {
                throw new Error('Authentication required. Please login as super admin.');
            }
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Standards data:', data);
            if (data.success) {
                displayBankingStandards(data.standards);
                const modal = new bootstrap.Modal(document.getElementById('bankingStandardsModal'));
                modal.show();
            } else {
                showAlert('error', 'Failed to load banking standards');
            }
        })
        .catch(error => {
            console.error('Banking standards error:', error);
            showAlert('error', 'Error loading banking standards: ' + error.message);
        });
}

// Make showBankingStandards immediately available
window.showBankingStandards = showBankingStandards;
console.log('=== showBankingStandards ASSIGNED ===', typeof window.showBankingStandards);

// Display banking standards
function displayBankingStandards(standards) {
    const container = document.getElementById('bankingStandardsContent');
    container.innerHTML = '';
    
    Object.keys(standards).forEach(standardKey => {
        const standard = standards[standardKey];
        
        const standardHtml = `
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">${standard.name}</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">${standard.description}</p>
                        <h6>{{ _lang('Requirements') }}:</h6>
                        <ul class="list-unstyled">
        `;
        
        Object.keys(standard.requirements).forEach(reqKey => {
            standardHtml += `<li><i class="fas fa-check text-success me-2"></i>${standard.requirements[reqKey]}</li>`;
        });
        
        standardHtml += `
                        </ul>
                    </div>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', standardHtml);
    });
}

// Refresh results
function refreshResults() {
    fetch('{{ route("security.testing.results") }}')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results) {
                displayResults(data.results);
            } else {
                // Hide results if no data
                document.getElementById('resultsDivider').style.display = 'none';
                document.getElementById('testSummary').style.display = 'none';
                document.getElementById('testCategoriesSection').style.display = 'none';
                document.getElementById('testCategories').innerHTML = '';
                showAlert('info', 'No test results found. Please run tests first.');
            }
        })
        .catch(error => {
            showAlert('error', 'Error refreshing results: ' + error.message);
        });
}

// Make refreshResults immediately available
window.refreshResults = refreshResults;
console.log('=== refreshResults ASSIGNED ===', typeof window.refreshResults);

// Show alert
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Remove existing alerts
    document.querySelectorAll('.alert').forEach(alert => alert.remove());
    
    // Add new alert at the top
    document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

// Show test history
function showTestHistory() {
    console.log('Opening test history modal...');
    loadTestHistory();
    const modal = new bootstrap.Modal(document.getElementById('testHistoryModal'));
    modal.show();
}

// Make showTestHistory immediately available
window.showTestHistory = showTestHistory;
console.log('=== showTestHistory ASSIGNED ===', typeof window.showTestHistory);

// Load test history
function loadTestHistory(page = 1) {
    const testType = document.getElementById('historyTestType').value;
    
    fetch(`{{ route("security.testing.history") }}?page=${page}&test_type=${testType}&per_page=10`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTestHistory(data.data);
                displayHistoryPagination(data.pagination);
                document.getElementById('clearHistoryBtn').style.display = data.data.length > 0 ? 'inline-block' : 'none';
            } else {
                document.getElementById('testHistoryContent').innerHTML = '<p class="text-muted text-center">No test history found.</p>';
            }
        })
        .catch(error => {
            showAlert('error', 'Error loading test history: ' + error.message);
        });
}

// Display test history
function displayTestHistory(history) {
    const container = document.getElementById('testHistoryContent');
    
    if (history.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No test history found.</p>';
        return;
    }
    
    let historyHtml = `
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ _lang('Test Type') }}</th>
                        <th>{{ _lang('Date') }}</th>
                        <th>{{ _lang('Duration') }}</th>
                        <th>{{ _lang('Results') }}</th>
                        <th>{{ _lang('Success Rate') }}</th>
                        <th>{{ _lang('Status') }}</th>
                        <th>{{ _lang('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    history.forEach(test => {
        const date = new Date(test.test_completed_at).toLocaleString();
        const statusClass = test.success_rate >= 90 ? 'success' : test.success_rate >= 75 ? 'warning' : 'danger';
        const statusText = test.success_rate >= 90 ? 'Excellent' : test.success_rate >= 75 ? 'Good' : 'Needs Attention';
        
        historyHtml += `
            <tr>
                <td>
                    <span class="badge bg-primary">${test.test_type.charAt(0).toUpperCase() + test.test_type.slice(1)}</span>
                </td>
                <td>${date}</td>
                <td>${formatDuration(test.duration_seconds)}</td>
                <td>
                    <small class="text-muted">
                        <span class="text-success">${test.passed_tests} passed</span> / 
                        <span class="text-danger">${test.failed_tests} failed</span> / 
                        <span class="text-info">${test.total_tests} total</span>
                    </small>
                </td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-${statusClass}" style="width: ${test.success_rate}%">
                            ${test.success_rate}%
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${statusClass}">${statusText}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewTestDetail(${test.id})">
                        <i class="fas fa-eye"></i> {{ _lang('View') }}
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTestResult(${test.id})">
                        <i class="fas fa-trash"></i> {{ _lang('Delete') }}
                    </button>
                </td>
            </tr>
        `;
    });
    
    historyHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = historyHtml;
}

// Display history pagination
function displayHistoryPagination(pagination) {
    const container = document.getElementById('historyPagination');
    
    if (pagination.last_page <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let paginationHtml = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    if (pagination.current_page > 1) {
        paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadTestHistory(${pagination.current_page - 1})">Previous</a>
            </li>
        `;
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.last_page; i++) {
        const activeClass = i === pagination.current_page ? 'active' : '';
        paginationHtml += `
            <li class="page-item ${activeClass}">
                <a class="page-link" href="#" onclick="loadTestHistory(${i})">${i}</a>
            </li>
        `;
    }
    
    // Next button
    if (pagination.current_page < pagination.last_page) {
        paginationHtml += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadTestHistory(${pagination.current_page + 1})">Next</a>
            </li>
        `;
    }
    
    paginationHtml += '</ul></nav>';
    container.innerHTML = paginationHtml;
}

// Filter test history
function filterTestHistory() {
    loadTestHistory(1);
}

// View test detail
function viewTestDetail(testId) {
    fetch(`{{ url('/admin/security/testing/detail') }}/${testId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTestDetail(data.data);
            } else {
                showAlert('error', 'Error loading test details');
            }
        })
        .catch(error => {
            showAlert('error', 'Error loading test details: ' + error.message);
        });
}

// Display test detail
function displayTestDetail(testData) {
    // Create modal for test details
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'testDetailModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Details - ${testData.test_type}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Date:</strong> ${new Date(testData.test_completed_at).toLocaleString()}
                        </div>
                        <div class="col-md-6">
                            <strong>Duration:</strong> ${formatDuration(testData.duration_seconds)}
                        </div>
                    </div>
                    <div id="testDetailCategories"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Display categories
    displayCategories(testData.test_summary, 'testDetailCategories');
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    // Remove modal when closed
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}

// Delete test result
function deleteTestResult(testId) {
    if (confirm('Are you sure you want to delete this test result?')) {
        fetch(`{{ url('/admin/security/testing/delete') }}/${testId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Test result deleted successfully');
                loadTestHistory();
            } else {
                showAlert('error', 'Error deleting test result');
            }
        })
        .catch(error => {
            showAlert('error', 'Error deleting test result: ' + error.message);
        });
    }
}

// Clear all history
function clearAllHistory() {
    if (confirm('Are you sure you want to delete all test history? This action cannot be undone.')) {
        // Implementation would go here
        showAlert('info', 'Feature coming soon');
    }
}

// Format duration helper
function formatDuration(seconds) {
    if (seconds < 60) {
        return seconds + 's';
    } else if (seconds < 3600) {
        return Math.round(seconds / 60 * 10) / 10 + 'm';
    } else {
        return Math.round(seconds / 3600 * 10) / 10 + 'h';
    }
}






// Ensure functions are globally available
window.runTests = runTests;
window.showBankingStandards = showBankingStandards;
window.showTestHistory = showTestHistory;
window.refreshResults = refreshResults;

// Load existing results on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Security testing page loaded - Functions available:', {
        runTests: typeof window.runTests,
        showBankingStandards: typeof window.showBankingStandards,
        showTestHistory: typeof window.showTestHistory,
        refreshResults: typeof window.refreshResults
    });
    refreshResults();
});

console.log('=== SCRIPT EXECUTION COMPLETED ===');
console.log('Final function check:', {
    runTests: typeof window.runTests,
    showBankingStandards: typeof window.showBankingStandards,
    showTestHistory: typeof window.showTestHistory,
    refreshResults: typeof window.refreshResults
});
</script>
@endsection

@section('styles')
<style>
.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
    color: white;
}

.btn-purple:hover {
    background-color: #5a2d91;
    border-color: #5a2d91;
    color: white;
}

.progress {
    height: 8px;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.badge {
    font-size: 0.75rem;
}

.spinner-border {
    width: 2rem;
    height: 2rem;
}

/* Test Category Cards */
.test-category-card {
    height: 100%;
}

.test-btn {
    height: 120px;
    border: 2px solid;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.test-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.test-btn-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    padding: 10px;
}

.test-btn-content i {
    opacity: 0.9;
}

.test-btn-content .fw-bold {
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.test-btn-content small {
    font-size: 0.75rem;
    opacity: 0.8;
    line-height: 1.2;
}

/* Results Section Styling */
#testSummary {
    margin-top: 2rem;
    animation: fadeInUp 0.5s ease-in-out;
}

#testCategories {
    margin-top: 1rem;
    animation: fadeInUp 0.6s ease-in-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Progress Enhancements */
.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .test-btn {
        height: 100px;
    }
    
    .test-btn-content .fw-bold {
        font-size: 0.8rem;
    }
    
    .test-btn-content small {
        font-size: 0.7rem;
    }
    
    .test-btn-content i {
        font-size: 1.5rem !important;
    }
}

/* Test result cards */
.test-result-card {
    border-left: 4px solid;
    margin-bottom: 1rem;
}

.test-result-card.success {
    border-left-color: #28a745;
}

.test-result-card.warning {
    border-left-color: #ffc107;
}

.test-result-card.danger {
    border-left-color: #dc3545;
}

/* Button loading states */
.test-btn:disabled,
.test-btn.disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

.test-btn.disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

/* Progress bar enhancements */
#testProgress {
    animation: slideDown 0.3s ease-in-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Alert positioning */
.alert {
    margin-bottom: 1rem;
    z-index: 1050;
    position: relative;
}

/* Spinner animation */
.fa-spinner {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
@endsection
