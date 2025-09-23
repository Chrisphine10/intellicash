<!DOCTYPE html>
<html>
<head>
    <title>{{ _lang('Repayment Receipt') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .receipt-title { font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .receipt-info { margin-bottom: 20px; }
        .receipt-info table { width: 100%; }
        .receipt-info td { padding: 5px; }
        .receipt-info .label { font-weight: bold; width: 30%; }
        .payment-details { margin-bottom: 20px; }
        .payment-details table { width: 100%; border-collapse: collapse; }
        .payment-details th, .payment-details td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .payment-details th { background-color: #f2f2f2; }
        .total { font-weight: bold; font-size: 14px; }
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
        <div class="receipt-title">{{ _lang('REPAYMENT RECEIPT') }}</div>
    </div>

    <div class="receipt-info">
        <table>
            <tr>
                <td class="label">{{ _lang('Receipt No') }}:</td>
                <td>RCP-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</td>
                <td class="label">{{ _lang('Payment Date') }}:</td>
                <td>{{ $payment->paid_at }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Loan ID') }}:</td>
                <td>{{ $payment->loan->loan_id }}</td>
                <td class="label">{{ _lang('Borrower') }}:</td>
                <td>{{ $payment->loan->borrower->first_name }} {{ $payment->loan->borrower->last_name }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Member No') }}:</td>
                <td>{{ $payment->loan->borrower->member_no }}</td>
                <td class="label">{{ _lang('Currency') }}:</td>
                <td>{{ $payment->loan->currency->name }}</td>
            </tr>
        </table>
    </div>

    <div class="payment-details">
        <table>
            <thead>
                <tr>
                    <th>{{ _lang('Description') }}</th>
                    <th>{{ _lang('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ _lang('Principal Payment') }}</td>
                    <td>{{ decimalPlace($payment->repayment_amount, currency($payment->loan->currency->name)) }}</td>
                </tr>
                <tr>
                    <td>{{ _lang('Interest Payment') }}</td>
                    <td>{{ decimalPlace($payment->interest, currency($payment->loan->currency->name)) }}</td>
                </tr>
                <tr>
                    <td>{{ _lang('Penalty Payment') }}</td>
                    <td>{{ decimalPlace($payment->late_penalties, currency($payment->loan->currency->name)) }}</td>
                </tr>
                <tr class="total">
                    <td>{{ _lang('Total Payment') }}</td>
                    <td>{{ decimalPlace($payment->total_amount, currency($payment->loan->currency->name)) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>{{ _lang('Thank you for your payment') }}</p>
        <p>{{ _lang('This is a computer generated receipt') }}</p>
        <button class="no-print" onclick="window.print()">{{ _lang('Print') }}</button>
    </div>
</body>
</html>
