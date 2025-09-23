<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VSLA Cycle Report - {{ $cycle->cycle_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 16px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fafafa;
        }
        .section h2 {
            color: #007bff;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .info-item h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 14px;
            font-weight: bold;
        }
        .info-item .value {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #cce5ff; color: #004085; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        @media print {
            body { background-color: white; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VSLA Cycle Report</h1>
            <p><strong>{{ $cycle->cycle_name }}</strong></p>
            <p>Member: {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})</p>
            <span class="status-badge status-{{ $cycle->status }}">
                {{ ucfirst($cycle->status) }}
            </span>
        </div>

        <!-- Cycle Overview -->
        <div class="section">
            <h2>📊 Cycle Overview</h2>
            <div class="info-grid">
                <div class="info-item">
                    <h3>Cycle Period</h3>
                    <div class="value">
                        {{ $cycle->start_date->format('M d, Y') }}<br>
                        <small>to {{ $cycle->end_date ? $cycle->end_date->format('M d, Y') : 'Ongoing' }}</small>
                    </div>
                </div>
                <div class="info-item">
                    <h3>Total Fund Available</h3>
                    <div class="value">{{ number_format($cycle->total_available_for_shareout, 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Your Shares</h3>
                    <div class="value">{{ number_format($reportData['expectedShareout']['shares_owned']) }}</div>
                </div>
                <div class="info-item">
                    <h3>Expected Return</h3>
                    <div class="value">{{ number_format($reportData['expectedShareout']['total_expected'], 2) }} {{ get_base_currency() }}</div>
                </div>
            </div>
        </div>

        <!-- Distribution Status -->
        @if($reportData['shareout'])
        <div class="section">
            <h2>✅ Distribution Completed</h2>
            <div class="highlight">
                <strong>Congratulations! Your cycle distribution has been processed.</strong>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <h3>Total Received</h3>
                    <div class="value">{{ number_format($reportData['shareout']->net_payout, 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Share Value</h3>
                    <div class="value">{{ number_format($reportData['shareout']->share_value_payout, 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Interest Earnings</h3>
                    <div class="value">{{ number_format($reportData['shareout']->profit_share, 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Welfare Return</h3>
                    <div class="value">{{ number_format($reportData['shareout']->welfare_refund, 2) }} {{ get_base_currency() }}</div>
                </div>
            </div>
        </div>
        @else
        <div class="section">
            <h2>⏳ Distribution Pending</h2>
            <div class="highlight">
                <strong>Your cycle distribution is pending. The amounts shown below are estimates based on your contributions.</strong>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <h3>Expected Share Value</h3>
                    <div class="value">{{ number_format($reportData['expectedShareout']['share_value'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Expected Interest</h3>
                    <div class="value">{{ number_format($reportData['expectedShareout']['interest_earnings'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Expected Welfare</h3>
                    <div class="value">{{ number_format($reportData['expectedShareout']['welfare_return'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Total Expected</h3>
                    <div class="value">{{ number_format($reportData['expectedShareout']['total_expected'], 2) }} {{ get_base_currency() }}</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Your Activity Summary -->
        <div class="section">
            <h2>📈 Your Activity Summary</h2>
            <table class="table">
                <tr>
                    <th>Activity Type</th>
                    <th>Count/Amount</th>
                </tr>
                <tr>
                    <td>Shares Purchased</td>
                    <td>{{ number_format($reportData['transactionSummary']['total_shares_purchased']) }}</td>
                </tr>
                <tr>
                    <td>Share Amount Paid</td>
                    <td>{{ number_format($reportData['transactionSummary']['total_shares_amount'], 2) }} {{ get_base_currency() }}</td>
                </tr>
                <tr>
                    <td>Welfare Contributed</td>
                    <td>{{ number_format($reportData['transactionSummary']['total_welfare_contributed'], 2) }} {{ get_base_currency() }}</td>
                </tr>
                <tr>
                    <td>Penalties Paid</td>
                    <td>{{ number_format($reportData['transactionSummary']['total_penalties_paid'], 2) }} {{ get_base_currency() }}</td>
                </tr>
                <tr>
                    <td>Loans Taken</td>
                    <td>{{ number_format($reportData['transactionSummary']['total_loans_taken'], 2) }} {{ get_base_currency() }}</td>
                </tr>
                <tr>
                    <td>Loan Repayments</td>
                    <td>{{ number_format($reportData['transactionSummary']['total_loans_repaid'], 2) }} {{ get_base_currency() }}</td>
                </tr>
                <tr>
                    <td><strong>Total Transactions</strong></td>
                    <td><strong>{{ number_format($reportData['transactionSummary']['transaction_count']) }}</strong></td>
                </tr>
            </table>
        </div>

        <!-- Current Account Balances -->
        @if(count($reportData['memberAccounts']) > 0)
        <div class="section">
            <h2>💰 Current Account Balances</h2>
            <div class="info-grid">
                @foreach($reportData['memberAccounts'] as $accountType => $accountData)
                <div class="info-item">
                    <h3>{{ $accountType }}</h3>
                    <div class="value">{{ number_format($accountData['balance'], 2) }} {{ get_base_currency() }}</div>
                    <small>Account: {{ $accountData['account']->account_number }}</small>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Loan Status -->
        @if($reportData['currentLoanStatus']['total_borrowed'] > 0)
        <div class="section">
            <h2>🏦 Your Loan Status</h2>
            <div class="info-grid">
                <div class="info-item">
                    <h3>Total Borrowed</h3>
                    <div class="value">{{ number_format($reportData['currentLoanStatus']['total_borrowed'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Total Repaid</h3>
                    <div class="value">{{ number_format($reportData['currentLoanStatus']['total_repaid'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Outstanding Balance</h3>
                    <div class="value">{{ number_format($reportData['currentLoanStatus']['outstanding_balance'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Repayment Rate</h3>
                    <div class="value">{{ $reportData['currentLoanStatus']['repayment_rate'] }}%</div>
                </div>
            </div>
        </div>
        @endif

        <!-- Group Summary -->
        <div class="section">
            <h2>👥 Group Summary</h2>
            <div class="info-grid">
                <div class="info-item">
                    <h3>Total Members</h3>
                    <div class="value">{{ $reportData['completeCycleReport']['group_totals']['total_members'] }}</div>
                </div>
                <div class="info-item">
                    <h3>Total Shares</h3>
                    <div class="value">{{ number_format($reportData['completeCycleReport']['group_totals']['total_shares']) }}</div>
                </div>
                <div class="info-item">
                    <h3>Total Contributions</h3>
                    <div class="value">{{ number_format($reportData['completeCycleReport']['group_totals']['total_share_amount'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Total Welfare</h3>
                    <div class="value">{{ number_format($reportData['completeCycleReport']['group_totals']['total_welfare'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Cycle Duration</h3>
                    <div class="value">{{ $reportData['completeCycleReport']['cycle_duration_days'] }} days</div>
                </div>
                <div class="info-item">
                    <h3>Average per Member</h3>
                    <div class="value">{{ number_format($reportData['completeCycleReport']['average_contribution_per_member'], 2) }} {{ get_base_currency() }}</div>
                </div>
            </div>
        </div>

        <!-- Cycle Performance -->
        <div class="section">
            <h2>📊 Cycle Performance</h2>
            <div class="info-grid">
                <div class="info-item">
                    <h3>Cycle Efficiency</h3>
                    <div class="value">{{ $reportData['cyclePerformance']['cycle_efficiency'] }}%</div>
                </div>
                <div class="info-item">
                    <h3>Profit Margin</h3>
                    <div class="value">{{ $reportData['cyclePerformance']['profit_margin'] }}%</div>
                </div>
                <div class="info-item">
                    <h3>Total Contributions</h3>
                    <div class="value">{{ number_format($reportData['cyclePerformance']['total_contributions'], 2) }} {{ get_base_currency() }}</div>
                </div>
                <div class="info-item">
                    <h3>Total Distributed</h3>
                    <div class="value">{{ number_format($reportData['cyclePerformance']['total_distributed'], 2) }} {{ get_base_currency() }}</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>{{ $tenant->name }}</strong></p>
            <p>This is an automated report generated on {{ now()->format('F d, Y \a\t h:i A') }}</p>
            <p>For questions or support, please contact your VSLA group administrator.</p>
        </div>
    </div>
</body>
</html>
