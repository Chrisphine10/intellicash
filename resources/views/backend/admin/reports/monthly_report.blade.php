@extends('layouts.app')

@section('title', _lang('Monthly Report'))

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
                    <span class="card-title">{{ _lang('Monthly Report') }}</span>
                    @if(isset($monthly_data))
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
                @if(!isset($monthly_data))
                <form class="validate" method="post" action="{{ route('reports.monthly_report') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Year') }}</label>
                                <select class="form-control" name="year" required>
                                    @for($i = date('Y'); $i >= date('Y') - 5; $i--)
                                    <option value="{{ $i }}" {{ $i == date('Y') ? 'selected' : '' }}>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Month') }}</label>
                                <select class="form-control" name="month" required>
                                    @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}" {{ $i == date('m') ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $i, 1)) }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary form-control">{{ _lang('Generate Report') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
                @else
                <div class="row">
                    <div class="col-md-12">
                        <h4>{{ _lang('Monthly Summary for') }} {{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $year }}</h4>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Loans Disbursed') }}</h5>
                                        <h3>{{ decimalPlace($monthly_data['loans_disbursed'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Loans Collected') }}</h5>
                                        <h3>{{ decimalPlace($monthly_data['loans_collected'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('New Borrowers') }}</h5>
                                        <h3>{{ $monthly_data['new_borrowers'] }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h5>{{ _lang('Fees Collected') }}</h5>
                                        <h3>{{ decimalPlace($monthly_data['fees_collected'], currency()) }}</h3>
                                    </div>
                                </div>
                            </div>
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
                    <title>Monthly Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .card { border: 1px solid #ddd; margin-bottom: 20px; }
                        .card-body { padding: 20px; }
                        .card-header { background-color: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }
                        .bg-primary { background-color: #007bff !important; }
                        .bg-success { background-color: #28a745 !important; }
                        .bg-info { background-color: #17a2b8 !important; }
                        .bg-warning { background-color: #ffc107 !important; }
                        .text-white { color: white !important; }
                        .row { display: flex; flex-wrap: wrap; }
                        .col-md-3 { flex: 0 0 25%; max-width: 25%; padding: 0 15px; }
                        .col-md-12 { flex: 0 0 100%; max-width: 100%; padding: 0 15px; }
                        h3, h4, h5 { margin: 10px 0; }
                        .form-group { margin-bottom: 15px; }
                    </style>
                </head>
                <body>
                    <h2>Monthly Report</h2>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    ${reportContent}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    function exportToCSV() {
        // Get the monthly data
        const monthlyData = @json($monthly_data ?? []);
        
        if (Object.keys(monthlyData).length === 0) {
            alert('No data available to export');
            return;
        }
        
        // Create CSV content
        let csvContent = 'Metric,Value\n';
        csvContent += `Loans Disbursed,${monthlyData.loans_disbursed || 0}\n`;
        csvContent += `Loans Collected,${monthlyData.loans_collected || 0}\n`;
        csvContent += `New Borrowers,${monthlyData.new_borrowers || 0}\n`;
        csvContent += `Fees Collected,${monthlyData.fees_collected || 0}\n`;
        
        // Create and download CSV file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'monthly_report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
@endpush
