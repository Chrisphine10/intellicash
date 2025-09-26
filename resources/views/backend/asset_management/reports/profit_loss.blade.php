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
                        <li class="breadcrumb-item active">{{ _lang('Profit & Loss Report') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Management - Profit & Loss Report') }}</h4>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('asset-reports.profit-loss') }}">
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
                                        <a href="{{ route('asset-reports.profit-loss') }}" class="btn btn-secondary">
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

    <!-- P&L Summary -->
    <div class="row">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-success bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-arrow-up font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-success mb-1">{{ formatAmount($leaseRevenue) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Total Revenue') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-danger bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-tools font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-danger mb-1">{{ formatAmount($maintenanceCosts) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Maintenance Costs') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded bg-warning bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-chart-line font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="text-warning mb-1">{{ formatAmount($depreciationCosts) }}</h5>
                            <p class="text-muted mb-0">{{ _lang('Depreciation') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avatar-sm rounded {{ $grossProfit >= 0 ? 'bg-success' : 'bg-danger' }} bg-soft">
                                <span class="avatar-title rounded">
                                    <i class="fas fa-{{ $grossProfit >= 0 ? 'check' : 'times' }} font-20"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="{{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }} mb-1">
                                {{ formatAmount($grossProfit) }}
                            </h5>
                            <p class="text-muted mb-0">{{ _lang('Gross Profit') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ _lang('Category Breakdown') }}</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Category') }}</th>
                                    <th class="text-end">{{ _lang('Revenue') }}</th>
                                    <th class="text-end">{{ _lang('Maintenance') }}</th>
                                    <th class="text-end">{{ _lang('Depreciation') }}</th>
                                    <th class="text-end">{{ _lang('Profit/Loss') }}</th>
                                    <th class="text-end">{{ _lang('Margin %') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categoryBreakdown as $breakdown)
                                <tr>
                                    <td>
                                        <div>
                                            <strong>{{ $breakdown['category']->name }}</strong>
                                            <br><small class="text-muted">{{ ucfirst($breakdown['category']->type) }}</small>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-success">{{ formatAmount($breakdown['revenue']) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-danger">{{ formatAmount($breakdown['maintenance']) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="text-warning">{{ formatAmount($breakdown['depreciation']) }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="{{ $breakdown['profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                            {{ formatAmount($breakdown['profit']) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($breakdown['revenue'] > 0)
                                            @php
                                                $margin = ($breakdown['profit'] / $breakdown['revenue']) * 100;
                                            @endphp
                                            <span class="{{ $margin >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($margin, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">{{ _lang('No data available') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="table-active">
                                    <th>{{ _lang('Total') }}</th>
                                    <th class="text-end">{{ formatAmount($leaseRevenue) }}</th>
                                    <th class="text-end">{{ formatAmount($maintenanceCosts) }}</th>
                                    <th class="text-end">{{ formatAmount($depreciationCosts) }}</th>
                                    <th class="text-end">{{ formatAmount($grossProfit) }}</th>
                                    <th class="text-end">
                                        @if($leaseRevenue > 0)
                                            @php
                                                $totalMargin = ($grossProfit / $leaseRevenue) * 100;
                                            @endphp
                                            <span class="{{ $totalMargin >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ number_format($totalMargin, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
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
                    <title>Profit & Loss Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .table th { background-color: #f2f2f2; }
                        .table-active { background-color: #f8f9fa; }
                        .text-success { color: #28a745; }
                        .text-danger { color: #dc3545; }
                        .text-muted { color: #6c757d; }
                        .text-end { text-align: right; }
                        .mb-3 { margin-bottom: 1rem; }
                        .card-title { font-weight: bold; margin-bottom: 1rem; }
                    </style>
                </head>
                <body>
                    <h2>Profit & Loss Report</h2>
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
        link.setAttribute('download', 'profit_loss_report.csv');
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
