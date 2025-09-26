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
					<span class="panel-title">{{ _lang('Account Balances') }}</span>
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
				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.account_balances') }}" autocomplete="off">
						<div class="row">
              				@csrf

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Account Type') }}</label>
									<select class="form-control auto-select" name="account_type_id" data-selected="{{ isset($account_type_id) ? $account_type_id : old('account_type_id') }}" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\SavingsProduct::with('currency')->active()->get() as $savings_product)
											<option value="{{ $savings_product->id }}">{{ $savings_product->name }} ({{ $savings_product->currency->name }})</option>
										@endforeach
										<option value="all">{{ _lang('All Account Type') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Member') }}</label>
									<select class="form-control select2-ajax" data-table="members" data-value="id" data-display="first_name" data-display2="last_name" 
										name="member_id" data-where="3" data-placeholder="{{ _lang('All Member') }}">
										@if(isset($member_id) && $member_id != '')
											<option value="{{ $member_id }}">{{ \App\Models\Member::find($member_id)->name ?? _lang('All Member') }}</option>
										@endif
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_date_format(); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Account Balances') }}</h4>
				   <h5>{{ _lang('Account Type').': '.$account_type }}</h5>
				   <h5>{{ _lang('Date').': '. date($date_format) }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Member') }}</th>
						<th>{{ _lang('Account Number') }}</th>
						<th class="text-right">{{ _lang('Balance') }}</th>
						<th class="text-right">{{ _lang('Loan Guarantee') }}</th>
						<th class="text-right">{{ _lang('Current Balance') }}</th>
					</thead>
					<tbody>
						@if(isset($accounts))
						@foreach($accounts as $account)
							<tr>
								<td>{{ $account->member->name }}</td>
								<td>{{ $account->account_number }} - {{ $account->savings_type->name }} ({{ $account->savings_type->currency->name }})</td>
								<td class="text-right">{{ decimalPlace($account->balance, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->balance - $account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
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
        const printElements = document.querySelectorAll('.btn, .report-params');
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
                    <title>Account Balances Report</title>
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
                    <h2>Account Balances Report</h2>
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
        link.setAttribute('download', 'account_balances_report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
@endpush