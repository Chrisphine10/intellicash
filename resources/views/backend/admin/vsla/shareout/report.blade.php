<!DOCTYPE html>
<html>
<head>
    <title>{{ _lang('VSLA Share-Out Report') }} - {{ $cycle->cycle_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .cycle-info { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; }
        .summary { margin-bottom: 20px; }
        .summary-item { display: inline-block; width: 150px; text-align: center; margin: 10px; }
        .summary-item h3 { margin: 5px 0; color: #333; }
        .summary-item small { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { background-color: #f9f9f9; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 10px; color: #666; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ _lang('VSLA Share-Out Report') }}</h1>
        <h2>{{ $cycle->cycle_name }}</h2>
        <p>{{ _lang('Generated on') }}: {{ now()->format('F d, Y \a\t g:i A') }}</p>
    </div>

    <div class="cycle-info">
        <h3>{{ _lang('Cycle Information') }}</h3>
        <table style="border: none;">
            <tr style="border: none;">
                <td style="border: none; width: 120px;"><strong>{{ _lang('Period') }}:</strong></td>
                <td style="border: none;">{{ $cycle->start_date->format('F d, Y') }} - {{ $cycle->end_date->format('F d, Y') }} ({{ $cycle->getFormattedDuration() }})</td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><strong>{{ _lang('Status') }}:</strong></td>
                <td style="border: none;">{{ ucfirst(str_replace('_', ' ', $cycle->status)) }}</td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><strong>{{ _lang('Share-Out Date') }}:</strong></td>
                <td style="border: none;">{{ $cycle->share_out_date ? $cycle->share_out_date->format('F d, Y \a\t g:i A') : 'N/A' }}</td>
            </tr>
            <tr style="border: none;">
                <td style="border: none;"><strong>{{ _lang('Members') }}:</strong></td>
                <td style="border: none;">{{ $cycle->getParticipatingMembersCount() }}</td>
            </tr>
            @if($cycle->notes)
            <tr style="border: none;">
                <td style="border: none;"><strong>{{ _lang('Notes') }}:</strong></td>
                <td style="border: none;">{{ $cycle->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="summary">
        <h3>{{ _lang('Financial Summary') }}</h3>
        <div style="display: flex; justify-content: space-around; margin: 20px 0;">
            <div class="summary-item">
                <h3>{{ currency($cycle->total_shares_contributed) }}</h3>
                <small>{{ _lang('Total Shares') }}</small>
            </div>
            <div class="summary-item">
                <h3>{{ currency($cycle->total_welfare_contributed) }}</h3>
                <small>{{ _lang('Total Welfare') }}</small>
            </div>
            <div class="summary-item">
                <h3>{{ currency($cycle->total_penalties_collected) }}</h3>
                <small>{{ _lang('Penalties') }}</small>
            </div>
            <div class="summary-item">
                <h3>{{ currency($cycle->total_loan_interest_earned) }}</h3>
                <small>{{ _lang('Loan Interest') }}</small>
            </div>
            <div class="summary-item">
                <h3>{{ currency($cycle->total_available_for_shareout) }}</h3>
                <small>{{ _lang('Available for Share-Out') }}</small>
            </div>
        </div>
    </div>

    @if($cycle->shareouts->count() > 0)
    <h3>{{ _lang('Member Share-Out Details') }}</h3>
    <table>
        <thead>
            <tr>
                <th>{{ _lang('Member') }}</th>
                <th class="text-right">{{ _lang('Shares Contributed') }}</th>
                <th class="text-center">{{ _lang('Share %') }}</th>
                <th class="text-right">{{ _lang('Welfare Contributed') }}</th>
                <th class="text-right">{{ _lang('Profit Share') }}</th>
                <th class="text-right">{{ _lang('Total Payout') }}</th>
                <th class="text-right">{{ _lang('Outstanding Loans') }}</th>
                <th class="text-right">{{ _lang('Net Payout') }}</th>
                <th class="text-center">{{ _lang('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cycle->shareouts->sortBy('member.first_name') as $shareout)
                <tr>
                    <td>{{ $shareout->member->first_name }} {{ $shareout->member->last_name }}</td>
                    <td class="text-right">{{ currency($shareout->total_shares_contributed) }}</td>
                    <td class="text-center">{{ $shareout->getFormattedSharePercentage() }}</td>
                    <td class="text-right">{{ currency($shareout->total_welfare_contributed) }}</td>
                    <td class="text-right">{{ currency($shareout->profit_share) }}</td>
                    <td class="text-right">{{ currency($shareout->total_payout) }}</td>
                    <td class="text-right">{{ currency($shareout->outstanding_loan_balance) }}</td>
                    <td class="text-right"><strong>{{ currency($shareout->net_payout) }}</strong></td>
                    <td class="text-center">{{ ucfirst($shareout->payout_status) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td><strong>{{ _lang('TOTALS') }}</strong></td>
                <td class="text-right"><strong>{{ currency($cycle->shareouts->sum('total_shares_contributed')) }}</strong></td>
                <td class="text-center"><strong>100.000%</strong></td>
                <td class="text-right"><strong>{{ currency($cycle->shareouts->sum('total_welfare_contributed')) }}</strong></td>
                <td class="text-right"><strong>{{ currency($cycle->shareouts->sum('profit_share')) }}</strong></td>
                <td class="text-right"><strong>{{ currency($cycle->shareouts->sum('total_payout')) }}</strong></td>
                <td class="text-right"><strong>{{ currency($cycle->shareouts->sum('outstanding_loan_balance')) }}</strong></td>
                <td class="text-right"><strong>{{ currency($cycle->shareouts->sum('net_payout')) }}</strong></td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <h3>{{ _lang('Calculation Methodology') }}</h3>
    <ul>
        <li>{{ _lang('Share percentage is calculated as: (Member Shares ÷ Total Shares) × 100') }}</li>
        <li>{{ _lang('Profit share is calculated as: (Total Profit × Share Percentage)') }}</li>
        <li>{{ _lang('Total profit = Total Available - Total Shares - Total Welfare') }}</li>
        <li>{{ _lang('Net payout = (Share Value + Welfare Refund + Profit Share) - Outstanding Loans') }}</li>
        <li>{{ _lang('Outstanding loans are deducted from member payouts to settle debts') }}</li>
    </ul>
    @endif

    <div class="footer">
        <p>{{ _lang('This report was generated by the IntelliCash VSLA module on') }} {{ now()->format('F d, Y \a\t g:i A') }}</p>
        <p>{{ _lang('Report covers the period from') }} {{ $cycle->start_date->format('F d, Y') }} {{ _lang('to') }} {{ $cycle->end_date->format('F d, Y') }}</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
            {{ _lang('Print Report') }}
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            {{ _lang('Close') }}
        </button>
    </div>
</body>
</html>
