<!DOCTYPE html>
<html>
<head>
    <title>{{ _lang('Loan Statement') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .statement-title { font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .loan-info { margin-bottom: 20px; }
        .loan-info table { width: 100%; }
        .loan-info td { padding: 5px; }
        .loan-info .label { font-weight: bold; width: 30%; }
        .payment-history { margin-bottom: 20px; }
        .payment-history table { width: 100%; border-collapse: collapse; }
        .payment-history th, .payment-history td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .payment-history th { background-color: #f2f2f2; }
        .summary { margin-top: 20px; }
        .summary table { width: 100%; }
        .summary td { padding: 5px; }
        .summary .label { font-weight: bold; width: 50%; }
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
        <div class="statement-title">{{ _lang('LOAN STATEMENT') }}</div>
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
                <td class="label">{{ _lang('Release Date') }}:</td>
                <td>{{ $loan->release_date }}</td>
                <td class="label">{{ _lang('Status') }}:</td>
                <td>
                    @if($loan->status == 1)
                        {{ _lang('Active') }}
                    @elseif($loan->status == 2)
                        {{ _lang('Fully Paid') }}
                    @elseif($loan->status == 3)
                        {{ _lang('Default') }}
                    @else
                        {{ _lang('Pending') }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="payment-history">
        <h4>{{ _lang('Payment History') }}</h4>
        <table>
            <thead>
                <tr>
                    <th>{{ _lang('Payment Date') }}</th>
                    <th>{{ _lang('Principal') }}</th>
                    <th>{{ _lang('Interest') }}</th>
                    <th>{{ _lang('Penalty') }}</th>
                    <th>{{ _lang('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loan->payments as $payment)
                <tr>
                    <td>{{ $payment->paid_at }}</td>
                    <td>{{ decimalPlace($payment->repayment_amount, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($payment->interest, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($payment->late_penalties, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($payment->total_amount, currency($loan->currency->name)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="summary">
        <h4>{{ _lang('Loan Summary') }}</h4>
        <table>
            <tr>
                <td class="label">{{ _lang('Applied Amount') }}:</td>
                <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Total Paid') }}:</td>
                <td>{{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Outstanding Balance') }}:</td>
                <td>{{ decimalPlace($loan->applied_amount - $loan->total_paid, currency($loan->currency->name)) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>{{ _lang('Generated on') }}: {{ date('Y-m-d H:i:s') }}</p>
        <button class="no-print" onclick="window.print()">{{ _lang('Print') }}</button>
    </div>
</body>
</html>
