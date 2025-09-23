@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('customer.vsla.cycle.index') }}">{{ _lang('VSLA Cycles') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $cycle->cycle_name }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title">{{ _lang('VSLA Cycle Report') }} - {{ $cycle->cycle_name }}</h4>
                        <p class="text-muted mb-0">{{ _lang('Member:') }} {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})</p>
                    </div>
                    <div>
                        <span class="badge badge-{{ $cycle->status == 'active' ? 'success' : ($cycle->status == 'completed' ? 'primary' : 'warning') }} badge-lg">
                            {{ ucfirst($cycle->status) }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Cycle Information -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Cycle Information') }}</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Cycle Period') }}</span>
                                        <span class="info-box-number">
                                            {{ $cycle->start_date->format('M d, Y') }}<br>
                                            <small>{{ _lang('to') }} {{ $cycle->end_date ? $cycle->end_date->format('M d, Y') : 'Ongoing' }}</small>
                                        </span>
                                    </div>
                                    <div class="info-box-icon">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Total Fund') }}</span>
                                        <span class="info-box-number">{{ number_format($cycle->total_available_for_shareout, 2) }} {{ get_base_currency() }}</span>
                                    </div>
                                    <div class="info-box-icon">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('My Shares') }}</span>
                                        <span class="info-box-number">{{ number_format($expectedShareout['shares_owned']) }}</span>
                                    </div>
                                    <div class="info-box-icon">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Expected Return') }}</span>
                                        <span class="info-box-number">{{ number_format($expectedShareout['total_expected'], 2) }} {{ get_base_currency() }}</span>
                                    </div>
                                    <div class="info-box-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Distribution Status -->
                @if($shareout)
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="alert alert-success">
                            <h5 class="alert-heading"><i class="fas fa-check-circle"></i> {{ _lang('Cycle Completed - Funds Distributed') }}</h5>
                            <hr>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>{{ _lang('Total Received:') }}</strong><br>
                                    <span class="h5 text-success">{{ number_format($shareout->net_payout, 2) }} {{ get_base_currency() }}</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>{{ _lang('Share Value:') }}</strong><br>
                                    {{ number_format($shareout->share_value_payout, 2) }} {{ get_base_currency() }}
                                </div>
                                <div class="col-md-3">
                                    <strong>{{ _lang('Interest Earnings:') }}</strong><br>
                                    {{ number_format($shareout->profit_share, 2) }} {{ get_base_currency() }}
                                </div>
                                <div class="col-md-3">
                                    <strong>{{ _lang('Welfare Return:') }}</strong><br>
                                    {{ number_format($shareout->welfare_refund, 2) }} {{ get_base_currency() }}
                                </div>
                            </div>
                            @if($shareout->notes)
                            <hr>
                            <p class="mb-0"><strong>{{ _lang('Notes:') }}</strong> {{ $shareout->notes }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="alert alert-warning">
                            <h5 class="alert-heading"><i class="fas fa-hourglass-half"></i> {{ _lang('Cycle Distribution Pending') }}</h5>
                            <p class="mb-0">{{ _lang('The fund distribution for this cycle has not been processed yet. The amounts shown below are estimates based on your contributions.') }}</p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Expected Distribution Breakdown -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang($shareout ? 'Actual Distribution Breakdown' : 'Expected Distribution Breakdown') }}</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-piggy-bank fa-3x text-primary mb-3"></i>
                                        <h6>{{ _lang('Share Value') }}</h6>
                                        <h4 class="text-primary">{{ number_format($shareout ? $shareout->share_value_payout : $expectedShareout['share_value'], 2) }} {{ get_base_currency() }}</h4>
                                        <small class="text-muted">{{ _lang('Based on your share contributions') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-percentage fa-3x text-success mb-3"></i>
                                        <h6>{{ _lang('Interest Earnings') }}</h6>
                                        <h4 class="text-success">{{ number_format($shareout ? $shareout->profit_share : $expectedShareout['interest_earnings'], 2) }} {{ get_base_currency() }}</h4>
                                        <small class="text-muted">{{ _lang('Your share of loan interest') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-heart fa-3x text-info mb-3"></i>
                                        <h6>{{ _lang('Welfare Return') }}</h6>
                                        <h4 class="text-info">{{ number_format($shareout ? $shareout->welfare_refund : $expectedShareout['welfare_return'], 2) }} {{ get_base_currency() }}</h4>
                                        <small class="text-muted">{{ _lang('Welfare fund distribution') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Summary -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('My Activity During This Cycle') }}</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ _lang('Activity') }}</th>
                                                <th class="text-right">{{ _lang('Amount/Count') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-chart-pie text-primary"></i> {{ _lang('Shares Purchased') }}</td>
                                                <td class="text-right font-weight-bold">{{ number_format($transactionSummary['total_shares_purchased']) }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-coins text-warning"></i> {{ _lang('Share Amount Paid') }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['total_shares_amount'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-heart text-info"></i> {{ _lang('Welfare Contributed') }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['total_welfare_contributed'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-exclamation-triangle text-danger"></i> {{ _lang('Penalties Paid') }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['total_penalties_paid'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ _lang('Loan Activity') }}</th>
                                                <th class="text-right">{{ _lang('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-hand-holding-usd text-success"></i> {{ _lang('Loans Taken') }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['total_loans_taken'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-money-check text-primary"></i> {{ _lang('Loan Repayments') }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['total_loans_repaid'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-list text-muted"></i> {{ _lang('Total Transactions') }}</td>
                                                <td class="text-right font-weight-bold">{{ number_format($transactionSummary['transaction_count']) }}</td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">&nbsp;</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Account Balances -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Current VSLA Account Balances') }}</h5>
                        <div class="row">
                            @foreach($memberAccounts as $accountType => $accountData)
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">{{ _lang($accountType) }}</h6>
                                        <h5 class="text-primary">{{ number_format($accountData['balance'], 2) }} {{ get_base_currency() }}</h5>
                                        <small class="text-muted">{{ _lang('Account No:') }} {{ $accountData['account']->account_number }}</small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Complete Cycle Report Section -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Complete Cycle Report') }}</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <h6 class="card-title text-info">
                                            <i class="fas fa-users"></i> {{ _lang('Group Summary') }}
                                        </h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Total Members') }}</small>
                                                <div class="h6">{{ $completeCycleReport['group_totals']['total_members'] }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Total Shares') }}</small>
                                                <div class="h6">{{ number_format($completeCycleReport['group_totals']['total_shares']) }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Total Contributions') }}</small>
                                                <div class="h6">{{ number_format($completeCycleReport['group_totals']['total_share_amount'], 2) }} {{ get_base_currency() }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Total Welfare') }}</small>
                                                <div class="h6">{{ number_format($completeCycleReport['group_totals']['total_welfare'], 2) }} {{ get_base_currency() }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-body">
                                        <h6 class="card-title text-success">
                                            <i class="fas fa-chart-line"></i> {{ _lang('Cycle Performance') }}
                                        </h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Efficiency') }}</small>
                                                <div class="h6">{{ $cyclePerformance['cycle_efficiency'] }}%</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Profit Margin') }}</small>
                                                <div class="h6">{{ $cyclePerformance['profit_margin'] }}%</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Duration') }}</small>
                                                <div class="h6">{{ $completeCycleReport['cycle_duration_days'] }} {{ _lang('days') }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">{{ _lang('Status') }}</small>
                                                <div class="h6">
                                                    <span class="badge badge-{{ $cyclePerformance['cycle_status'] == 'active' ? 'success' : ($cyclePerformance['cycle_status'] == 'completed' ? 'primary' : 'warning') }}">
                                                        {{ ucfirst($cyclePerformance['cycle_status']) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Loan Status -->
                @if($currentLoanStatus['total_borrowed'] > 0)
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Your Loan Status') }}</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-warning">{{ _lang('Total Borrowed') }}</h6>
                                        <h5 class="text-warning">{{ number_format($currentLoanStatus['total_borrowed'], 2) }} {{ get_base_currency() }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-success">{{ _lang('Total Repaid') }}</h6>
                                        <h5 class="text-success">{{ number_format($currentLoanStatus['total_repaid'], 2) }} {{ get_base_currency() }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-danger">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-danger">{{ _lang('Outstanding') }}</h6>
                                        <h5 class="text-danger">{{ number_format($currentLoanStatus['outstanding_balance'], 2) }} {{ get_base_currency() }}</h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-info">{{ _lang('Repayment Rate') }}</h6>
                                        <h5 class="text-info">{{ $currentLoanStatus['repayment_rate'] }}%</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Notification Actions -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Get Complete Report') }}</h5>
                        <div class="alert alert-info">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="alert-heading mb-1">
                                        <i class="fas fa-envelope"></i> {{ _lang('Receive Complete Cycle Report') }}
                                    </h6>
                                    <p class="mb-0">{{ _lang('Get a detailed cycle report sent to your email and phone with all the information about your VSLA participation.') }}</p>
                                </div>
                                <div>
                                    <button class="btn btn-primary" onclick="sendCycleReport({{ $cycle->id }})">
                                        <i class="fas fa-paper-plane"></i> {{ _lang('Send Report') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="row">
                    <div class="col-lg-12">
                        <a href="{{ route('customer.vsla.cycle.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Cycle Reports') }}
                        </a>
                        @if($shareout)
                        <button class="btn btn-primary ml-2" onclick="window.print()">
                            <i class="fas fa-print"></i> {{ _lang('Print Report') }}
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
function sendCycleReport(cycleId) {
    if (confirm('{{ _lang("Are you sure you want to send the complete cycle report to your email and phone?") }}')) {
        // Show loading state
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {{ _lang("Sending...") }}';
        button.disabled = true;
        
        // Send AJAX request
        const url = '{{ route("customer.vsla.cycle.send_report", ["cycle_id" => ":cycle_id"]) }}'.replace(':cycle_id', cycleId);
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('{{ _lang("Cycle report sent successfully! Check your email and phone for the complete report.") }}');
            } else {
                alert('{{ _lang("Error sending report: ") }}' + (data.error || '{{ _lang("Unknown error") }}'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('{{ _lang("Error sending report. Please try again.") }}');
        })
        .finally(() => {
            // Reset button state
            button.innerHTML = originalText;
            button.disabled = false;
        });
    }
}
</script>
@endsection

@section('css-script')
<style>
.info-box {
    display: block;
    min-height: 90px;
    background: #fff;
    width: 100%;
    box-shadow: 0 1px 1px rgba(0,0,0,0.1);
    border-radius: 2px;
    margin-bottom: 15px;
    position: relative;
    border-left: 3px solid #007bff;
}

.info-box-content {
    padding: 5px 10px;
    margin-left: 70px;
}

.info-box-icon {
    border-top-left-radius: 2px;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-bottom-left-radius: 2px;
    display: block;
    float: left;
    height: 90px;
    width: 70px;
    text-align: center;
    font-size: 45px;
    line-height: 90px;
    background: rgba(0,0,0,0.2);
    color: #fff;
}

.info-box-text {
    display: block;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.info-box-number {
    display: block;
    font-weight: bold;
    font-size: 18px;
}

.badge-lg {
    font-size: 0.9em;
    padding: 0.5rem 0.75rem;
}

@media print {
    .btn, .breadcrumb, .card-header .d-flex > div:last-child {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
