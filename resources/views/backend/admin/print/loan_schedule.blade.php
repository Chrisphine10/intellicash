<!DOCTYPE html>
<html>
<head>
    <title>{{ _lang('Loan Schedule') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .schedule-title { font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .loan-info { margin-bottom: 20px; }
        .loan-info table { width: 100%; }
        .loan-info td { padding: 5px; }
        .loan-info .label { font-weight: bold; width: 30%; }
        .schedule-table { margin-bottom: 20px; }
        .schedule-table table { width: 100%; border-collapse: collapse; }
        .schedule-table th, .schedule-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .schedule-table th { background-color: #f2f2f2; }
        .schedule-table .paid { background-color: #d4edda; }
        .schedule-table .overdue { background-color: #f8d7da; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ get_setting('site_title') }}</div>
        <div class="schedule-title">{{ _lang('LOAN REPAYMENT SCHEDULE') }}</div>
    </div>

    <div class="loan-info">
        <table>
            <tr>
                <td class="label">{{ _lang('Loan ID') }}:</td>
                <td>{{ $loan->loan_id }}</td>
                <td class="label">{{ _lang('Borrower') }}:</td>
                <td>{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Member No') }}:</td>
                <td>{{ $loan->borrower->member_no }}</td>
                <td class="label">{{ _lang('Loan Product') }}:</td>
                <td>{{ $loan->loan_product->name }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Applied Amount') }}:</td>
                <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                <td class="label">{{ _lang('Currency') }}:</td>
                <td>{{ $loan->currency->name }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Interest Rate') }}:</td>
                <td>{{ $loan->loan_product->interest_rate }}%</td>
                <td class="label">{{ _lang('Term') }}:</td>
                <td>{{ $loan->loan_product->term }} {{ $loan->loan_product->term_period }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Release Date') }}:</td>
                <td>{{ $loan->release_date }}</td>
                <td class="label">{{ _lang('First Payment Date') }}:</td>
                <td>{{ $loan->first_payment_date }}</td>
            </tr>
        </table>
    </div>

    <div class="schedule-table">
        <table>
            <thead>
                <tr>
                    <th>{{ _lang('Installment') }}</th>
                    <th>{{ _lang('Due Date') }}</th>
                    <th>{{ _lang('Principal') }}</th>
                    <th>{{ _lang('Interest') }}</th>
                    <th>{{ _lang('Penalty') }}</th>
                    <th>{{ _lang('Total Amount') }}</th>
                    <th>{{ _lang('Balance') }}</th>
                    <th>{{ _lang('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($repayments as $index => $repayment)
                <tr class="{{ $repayment->status == 1 ? 'paid' : ($repayment->repayment_date < date('Y-m-d') ? 'overdue' : '') }}">
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $repayment->repayment_date }}</td>
                    <td>{{ decimalPlace($repayment->principal_amount, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($repayment->interest, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($repayment->penalty, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($repayment->amount_to_pay, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($repayment->balance, currency($loan->currency->name)) }}</td>
                    <td>
                        @if($repayment->status == 1)
                            {{ _lang('Paid') }}
                        @elseif($repayment->repayment_date < date('Y-m-d'))
                            {{ _lang('Overdue') }}
                        @else
                            {{ _lang('Pending') }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>{{ _lang('Generated on') }}: {{ date('Y-m-d H:i:s') }}</p>
        <button class="no-print" onclick="window.print()">{{ _lang('Print') }}</button>
    </div>
</body>
</html>
