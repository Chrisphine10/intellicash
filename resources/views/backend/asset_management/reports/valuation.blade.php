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
                        <li class="breadcrumb-item active">{{ _lang('Asset Valuation Report') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Management - Valuation Report') }}</h4>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('asset-reports.valuation') }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="as_of_date">{{ _lang('As of Date') }}</label>
                                    <input type="date" class="form-control" id="as_of_date" name="as_of_date" 
                                           value="{{ $date }}" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-filter me-1"></i> {{ _lang('Filter') }}
                                        </button>
                                        <a href="{{ route('asset-reports.valuation') }}" class="btn btn-secondary">
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

    <!-- Summary Cards -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-primary bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-shopping-cart font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-primary mb-1">{{ formatAmount($totalPurchaseValue) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Purchase Value') }}</p>
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
                            <h5 class="text-success mb-1">{{ formatAmount($totalCurrentValue) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Current Value') }}</p>
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
                            <div class="avatar-sm rounded bg-warning bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-arrow-down font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-warning mb-1">{{ formatAmount($totalDepreciation) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Depreciation') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assets Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Valuation Details') }}</h4>
                </div>
                <div class="card-body">
                    @if($assets->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Asset Code') }}</th>
                                        <th>{{ _lang('Asset Name') }}</th>
                                        <th>{{ _lang('Category') }}</th>
                                        <th>{{ _lang('Purchase Date') }}</th>
                                        <th>{{ _lang('Purchase Value') }}</th>
                                        <th>{{ _lang('Current Value') }}</th>
                                        <th>{{ _lang('Depreciation') }}</th>
                                        <th>{{ _lang('Depreciation %') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assets as $asset)
                                    <tr>
                                        <td><strong>{{ $asset->asset_code }}</strong></td>
                                        <td>{{ $asset->name }}</td>
                                        <td>{{ $asset->category->name }}</td>
                                        <td>{{ $asset->purchase_date }}</td>
                                        <td>{{ formatAmount($asset->purchase_value) }}</td>
                                        <td>{{ formatAmount($asset->current_value) }}</td>
                                        <td>{{ formatAmount($asset->depreciation) }}</td>
                                        <td>
                                            @php
                                                $depreciationPercent = $asset->purchase_value > 0 ? 
                                                    (($asset->depreciation / $asset->purchase_value) * 100) : 0;
                                            @endphp
                                            <span class="badge badge-{{ $depreciationPercent > 50 ? 'danger' : ($depreciationPercent > 25 ? 'warning' : 'success') }}">
                                                {{ number_format($depreciationPercent, 1) }}%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($asset->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No assets found') }}</h5>
                            <p class="text-muted">{{ _lang('No assets were found for valuation.') }}</p>
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
                        <h5 class="card-title mb-0">{{ _lang('Export & Print Options') }}</h5>
                        <div>
                            <button class="btn btn-info me-2" onclick="printReport()">
                                <i class="fas fa-print me-1"></i> {{ _lang('Print Report') }}
                            </button>
                            <button class="btn btn-success me-2" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-1"></i> {{ _lang('Export PDF') }}
                            </button>
                            <button class="btn btn-primary me-2" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-1"></i> {{ _lang('Export Excel') }}
                            </button>
                            <button class="btn btn-secondary" onclick="exportToCSV()">
                                <i class="fas fa-file-csv me-1"></i> {{ _lang('Export CSV') }}
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
    function printReport() {
        // Hide buttons and other non-printable elements
        const printElements = document.querySelectorAll('.btn, .page-title-box, .card-header:not(.report-header)');
        printElements.forEach(el => el.style.display = 'none');
        
        // Print the page
        window.print();
        
        // Restore elements after printing
        printElements.forEach(el => el.style.display = '');
    }

    function exportToPDF() {
        // Create a new window with the report content
        const printWindow = window.open('', '_blank');
        const reportContent = document.querySelector('.container-fluid').innerHTML;
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Asset Valuation Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .table th { background-color: #f2f2f2; }
                        .badge { padding: 4px 8px; border-radius: 4px; color: white; }
                        .badge-success { background-color: #28a745; }
                        .badge-warning { background-color: #ffc107; color: black; }
                        .badge-danger { background-color: #dc3545; }
                        .badge-secondary { background-color: #6c757d; }
                        .text-center { text-align: center; }
                        .text-right { text-align: right; }
                        .mb-3 { margin-bottom: 1rem; }
                        .card-title { font-weight: bold; margin-bottom: 1rem; }
                    </style>
                </head>
                <body>
                    <h2>Asset Valuation Report</h2>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    ${reportContent}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    function exportToExcel() {
        // Get table data
        const table = document.querySelector('.table');
        const rows = Array.from(table.querySelectorAll('tr'));
        
        let csvContent = '';
        rows.forEach(row => {
            const cells = Array.from(row.querySelectorAll('th, td'));
            const rowData = cells.map(cell => {
                // Clean up cell content
                let content = cell.textContent.trim();
                // Remove badge styling and keep only text
                content = content.replace(/\s+/g, ' ');
                return `"${content}"`;
            });
            csvContent += rowData.join(',') + '\n';
        });
        
        // Create and download CSV file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'asset_valuation_report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportToCSV() {
        exportToExcel(); // Same functionality as Excel export
    }
</script>
@endpush
