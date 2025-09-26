@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<!-- Back Button -->
		<div class="mb-3">
			<a href="{{ route('dashboard.index') }}" class="btn btn-secondary">
				<i class="fa fa-arrow-left"></i> Back to Dashboard
			</a>
		</div>
		
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-center">
					<span class="panel-title">{{ _lang('Bank Balances') }}</span>
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
				</div>
			</div>

			<div class="card-body">
				@php $date_format = get_date_format(); @endphp
				@php $currency = currency(get_base_currency()); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Bank Account Balances') }}</h4>
				   <p>{{ _lang('Date').': '. date($date_format) }}</p>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Bank Name') }}</th>
						<th>{{ _lang('Account Name') }}</th>
						<th>{{ _lang('Account Number') }}</th>
						<th>{{ _lang('Currency') }}</th>
						<th class="text-right pr-4">{{ _lang('Current Balance') }}</th>
					</thead>
					<tbody>
						@if(isset($accounts))
						@foreach($accounts as $account)
							<tr>
								<td>{{ $account->bank_name }}</td>										
								<td>{{ $account->account_name }}</td>										
								<td>{{ $account->account_number }}</td>										
								<td>{{ $account->currency->name }}</td>										
								<td class="text-right pr-4">{{ decimalPlace($account->balance, currency($account->currency->name)) }}</td>										
							</tr>
						@endforeach
						@endif
				    </tbody>
				</table>
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
                    <title>Bank Balances Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .table th { background-color: #f2f2f2; }
                        .text-right { text-align: right; }
                        .report-header { text-align: center; margin-bottom: 20px; }
                        .logo { max-height: 50px; }
                    </style>
                </head>
                <body>
                    <h2>Bank Balances Report</h2>
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
        const table = document.querySelector('.report-table');
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
        link.setAttribute('download', 'bank_balances_report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
@endpush