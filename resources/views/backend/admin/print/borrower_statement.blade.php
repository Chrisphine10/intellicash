<!DOCTYPE html>
<html>
<head>
    <title>{{ _lang('Borrower Statement') }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .statement-title { font-size: 16px; font-weight: bold; margin-bottom: 20px; }
        .borrower-info { margin-bottom: 20px; }
        .borrower-info table { width: 100%; }
        .borrower-info td { padding: 5px; }
        .borrower-info .label { font-weight: bold; width: 30%; }
        .loans-summary { margin-bottom: 20px; }
        .loans-summary table { width: 100%; border-collapse: collapse; }
        .loans-summary th, .loans-summary td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .loans-summary th { background-color: #f2f2f2; }
        .overall-summary { margin-top: 20px; }
        .overall-summary table { width: 100%; }
        .overall-summary td { padding: 5px; }
        .overall-summary .label { font-weight: bold; width: 50%; }
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
        <div class="statement-title">{{ _lang('BORROWER STATEMENT') }}</div>
    </div>

    <div class="borrower-info">
        <table>
            <tr>
                <td class="label">{{ _lang('Member No') }}:</td>
                <td>{{ $member->member_no }}</td>
                <td class="label">{{ _lang('Name') }}:</td>
                <td>{{ $member->first_name }} {{ $member->last_name }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Email') }}:</td>
                <td>{{ $member->email }}</td>
                <td class="label">{{ _lang('Mobile') }}:</td>
                <td>{{ $member->mobile }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Gender') }}:</td>
                <td>{{ $member->gender }}</td>
                <td class="label">{{ _lang('Address') }}:</td>
                <td>{{ $member->address }}</td>
            </tr>
        </table>
    </div>

    <div class="loans-summary">
        <h4>{{ _lang('Loan Summary') }}</h4>
        <table>
            <thead>
                <tr>
                    <th>{{ _lang('Loan ID') }}</th>
                    <th>{{ _lang('Product') }}</th>
                    <th>{{ _lang('Applied Amount') }}</th>
                    <th>{{ _lang('Total Paid') }}</th>
                    <th>{{ _lang('Outstanding') }}</th>
                    <th>{{ _lang('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loans as $loan)
                <tr>
                    <td>{{ $loan->loan_id }}</td>
                    <td>{{ $loan->loan_product->name }}</td>
                    <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}</td>
                    <td>{{ decimalPlace($loan->applied_amount - $loan->total_paid, currency($loan->currency->name)) }}</td>
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
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="overall-summary">
        <h4>{{ _lang('Overall Summary') }}</h4>
        <table>
            <tr>
                <td class="label">{{ _lang('Total Loans') }}:</td>
                <td>{{ $loans->count() }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Active Loans') }}:</td>
                <td>{{ $loans->where('status', 1)->count() }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Fully Paid Loans') }}:</td>
                <td>{{ $loans->where('status', 2)->count() }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Total Borrowed') }}:</td>
                <td>{{ decimalPlace($loans->sum('applied_amount'), currency()) }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Total Paid') }}:</td>
                <td>{{ decimalPlace($loans->sum('total_paid'), currency()) }}</td>
            </tr>
            <tr>
                <td class="label">{{ _lang('Total Outstanding') }}:</td>
                <td>{{ decimalPlace($loans->sum('applied_amount') - $loans->sum('total_paid'), currency()) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>{{ _lang('Generated on') }}: {{ date('Y-m-d H:i:s') }}</p>
        <button class="no-print" onclick="window.print()">{{ _lang('Print') }}</button>
    </div>
</body>
</html>
