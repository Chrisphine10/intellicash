@extends('layouts.app')

@section('title', _lang('Portfolio At Risk Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="{{ route('dashboard.index') }}" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="card-title">{{ _lang('Portfolio At Risk (PAR) Report') }}</span>
                    @if(isset($report_data))
                    <div>
                        <button class="btn btn-info btn-sm me-2" onclick="printReport()">
                            <i class="fas fa-print me-1"></i> {{ _lang('Print') }}
                        </button>
                        <button class="btn btn-success btn-sm me-2" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-1"></i> {{ _lang('PDF') }}
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="exportToCSV()">
                            <i class="fas fa-file-csv me-1"></i> {{ _lang('CSV') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if(!isset($report_data))
                <form class="validate" method="post" action="{{ route('reports.portfolio_at_risk_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ _lang('Generate Report') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
                @else
                <div class="row">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Borrower') }}</th>
                                        <th>{{ _lang('Loan Product') }}</th>
                                        <th>{{ _lang('Repayment Date') }}</th>
                                        <th>{{ _lang('Days Overdue') }}</th>
                                        <th>{{ _lang('Principal Due') }}</th>
                                        <th>{{ _lang('Interest Due') }}</th>
                                        <th>{{ _lang('Penalty Due') }}</th>
                                        <th>{{ _lang('Total Due') }}</th>
                                        <th>{{ _lang('Risk Level') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($report_data as $repayment)
                                    <tr>
                                        <td>{{ $repayment->loan->loan_id }}</td>
                                        <td>{{ $repayment->loan->borrower->first_name }} {{ $repayment->loan->borrower->last_name }}</td>
                                        <td>{{ $repayment->loan->loan_product->name }}</td>
                                        <td>{{ $repayment->repayment_date }}</td>
                                        <td>{{ $repayment->days_overdue }}</td>
                                        <td>{{ decimalPlace($repayment->principal_amount, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->interest, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->penalty, currency()) }}</td>
                                        <td>{{ decimalPlace($repayment->amount_to_pay, currency()) }}</td>
                                        <td>
                                            @if($repayment->days_overdue > 90)
                                                <span class="badge badge-danger">{{ _lang('High Risk') }}</span>
                                            @elseif($repayment->days_overdue > 30)
                                                <span class="badge badge-warning">{{ _lang('Medium Risk') }}</span>
                                            @else
                                                <span class="badge badge-info">{{ _lang('Low Risk') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function printReport() {
        // Hide buttons and other non-printable elements
        const printElements = document.querySelectorAll('.btn');
        printElements.forEach(el => el.style.display = 'none');
        
        // Print the page
        window.print();
        
        // Restore elements after printing
        printElements.forEach(el => el.style.display = '');
    }

    function exportToPDF() {
        // Create a new window with the report content
        const printWindow = window.open('', '_blank');
        const reportContent = document.querySelector('.card-body').innerHTML;
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Portfolio At Risk Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .table th { background-color: #f2f2f2; }
                        .text-right { text-align: right; }
                        .badge { padding: 4px 8px; border-radius: 4px; color: white; }
                        .badge-danger { background-color: #dc3545; }
                        .badge-warning { background-color: #ffc107; color: black; }
                        .badge-success { background-color: #28a745; }
                        .card { border: 1px solid #ddd; margin-bottom: 20px; }
                        .card-body { padding: 20px; }
                        .card-header { background-color: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }
                    </style>
                </head>
                <body>
                    <h2>Portfolio At Risk Report</h2>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    ${reportContent}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    function exportToCSV() {
        // Get table data
        const table = document.querySelector('.table');
        if (!table) {
            alert('No data table found to export');
            return;
        }
        
        const rows = Array.from(table.querySelectorAll('tr'));
        
        let csvContent = '';
        rows.forEach(row => {
            const cells = Array.from(row.querySelectorAll('th, td'));
            const rowData = cells.map(cell => {
                // Clean up cell content
                let content = cell.textContent.trim();
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
        link.setAttribute('download', 'portfolio_at_risk_report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
@endpush
