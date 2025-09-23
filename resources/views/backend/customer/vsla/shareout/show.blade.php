@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('customer.vsla.cycles.index') }}">{{ _lang('VSLA Cycles') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $cycleModel->cycle_name }}</li>
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
                        <h4 class="card-title">{{ _lang('VSLA Cycle Report') }} - {{ $cycleModel->cycle_name }}</h4>
                        <p class="text-muted mb-0">{{ _lang('Member:') }} {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no }})</p>
                    </div>
                    <div>
                        <span class="badge badge-{{ $cycleModel->status == 'active' ? 'success' : ($cycleModel->status == 'completed' ? 'primary' : 'warning') }} badge-lg">
                            {{ ucfirst($cycleModel->status) }}
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
                                            {{ $cycleModel->start_date->format('M d, Y') }}<br>
                                            <small>{{ _lang('to') }} {{ $cycleModel->end_date ? $cycleModel->end_date->format('M d, Y') : 'Ongoing' }}</small>
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
                                        <span class="info-box-number">{{ number_format($cycleModel->total_available_for_shareout, 2) }} {{ get_base_currency() }}</span>
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

                <!-- Shareout Status -->
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

                <!-- Expected Shareout Breakdown -->
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

                <!-- Group Summary Section -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Group Cycle Summary') }}</h5>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> {{ _lang('This section shows the overall performance of all members in this cycle.') }}
                        </div>
                        
                        <!-- Group Statistics -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                        <h6>{{ _lang('Total Members') }}</h6>
                                        <h4 class="text-primary">{{ number_format($groupSummary['total_members']) }}</h4>
                                        <small class="text-muted">{{ _lang('Participating members') }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-chart-pie fa-2x text-success mb-2"></i>
                                        <h6>{{ _lang('Total Shares') }}</h6>
                                        <h4 class="text-success">{{ number_format($groupSummary['total_shares_sold']) }}</h4>
                                        <small class="text-muted">{{ number_format($groupSummary['total_share_value'], 2) }} {{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-hand-holding-usd fa-2x text-info mb-2"></i>
                                        <h6>{{ _lang('Loans Issued') }}</h6>
                                        <h4 class="text-info">{{ number_format($groupSummary['total_loans_issued']) }}</h4>
                                        <small class="text-muted">{{ number_format($groupSummary['total_loan_amount'], 2) }} {{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-coins fa-2x text-warning mb-2"></i>
                                        <h6>{{ _lang('Total Fund') }}</h6>
                                        <h4 class="text-warning">{{ number_format($groupSummary['cycle_totals']['total_fund'], 2) }}</h4>
                                        <small class="text-muted">{{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Breakdown -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">{{ _lang('Fund Composition') }}</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <td><i class="fas fa-piggy-bank text-primary"></i> {{ _lang('Share Contributions') }}</td>
                                                        <td class="text-right font-weight-bold">{{ number_format($groupSummary['cycle_totals']['shares'], 2) }} {{ get_base_currency() }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-heart text-info"></i> {{ _lang('Welfare Fund') }}</td>
                                                        <td class="text-right">{{ number_format($groupSummary['cycle_totals']['welfare'], 2) }} {{ get_base_currency() }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-percentage text-success"></i> {{ _lang('Interest Earned') }}</td>
                                                        <td class="text-right">{{ number_format($groupSummary['cycle_totals']['interest'], 2) }} {{ get_base_currency() }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-exclamation-triangle text-warning"></i> {{ _lang('Penalties') }}</td>
                                                        <td class="text-right">{{ number_format($groupSummary['cycle_totals']['penalties'], 2) }} {{ get_base_currency() }}</td>
                                                    </tr>
                                                    <tr class="border-top">
                                                        <td><strong>{{ _lang('Total Available') }}</strong></td>
                                                        <td class="text-right"><strong>{{ number_format($groupSummary['cycle_totals']['total_fund'], 2) }} {{ get_base_currency() }}</strong></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">{{ _lang('Transaction Activity') }}</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <td><i class="fas fa-list text-secondary"></i> {{ _lang('Total Transactions') }}</td>
                                                        <td class="text-right font-weight-bold">{{ number_format($groupSummary['total_transactions']) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-chart-pie text-primary"></i> {{ _lang('Share Purchases') }}</td>
                                                        <td class="text-right">{{ number_format($groupSummary['total_shares_sold']) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-hand-holding-usd text-success"></i> {{ _lang('Loans Issued') }}</td>
                                                        <td class="text-right">{{ number_format($groupSummary['total_loans_issued']) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td><i class="fas fa-money-check text-info"></i> {{ _lang('Loan Repayments') }}</td>
                                                        <td class="text-right">{{ number_format($groupSummary['total_loan_repayments'], 2) }} {{ get_base_currency() }}</td>
                                                    </tr>
                                                    <tr class="border-top">
                                                        <td><strong>{{ _lang('Net Interest') }}</strong></td>
                                                        <td class="text-right"><strong class="text-success">{{ number_format($groupSummary['net_loan_interest'], 2) }} {{ get_base_currency() }}</strong></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Contributors -->
                        <div class="row mb-4">
                            <div class="col-lg-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">{{ _lang('Top Contributors') }} ({{ _lang('Top 10') }})</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>{{ _lang('Member') }}</th>
                                                        <th class="text-center">{{ _lang('Shares') }}</th>
                                                        <th class="text-right">{{ _lang('Share Amount') }}</th>
                                                        <th class="text-right">{{ _lang('Welfare') }}</th>
                                                        <th class="text-center">{{ _lang('Share %') }}</th>
                                                        <th class="text-center">{{ _lang('Status') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse($participatingMembers as $memberData)
                                                    <tr class="{{ $memberData['member']->id == $member->id ? 'table-warning' : '' }}">
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                @if($memberData['member']->id == $member->id)
                                                                <i class="fas fa-user text-warning mr-2" data-toggle="tooltip" title="{{ _lang('This is you') }}"></i>
                                                                @endif
                                                                <div>
                                                                    <strong>{{ $memberData['member']->first_name }} {{ $memberData['member']->last_name }}</strong>
                                                                    @if($memberData['member']->id == $member->id)
                                                                    <small class="text-warning">({{ _lang('You') }})</small>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-primary">{{ number_format($memberData['shares']) }}</span>
                                                        </td>
                                                        <td class="text-right">{{ number_format($memberData['share_amount'], 2) }}</td>
                                                        <td class="text-right">{{ number_format($memberData['welfare'], 2) }}</td>
                                                        <td class="text-center">
                                                            <span class="badge badge-info">{{ number_format($memberData['share_percentage'], 1) }}%</span>
                                                        </td>
                                                        <td class="text-center">
                                                            @if($memberData['shareout'])
                                                            <span class="badge badge-success">{{ _lang('Paid') }}</span>
                                                            @else
                                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted">{{ _lang('No member data available') }}</td>
                                                    </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                        @if(count($participatingMembers) >= 10)
                                        <div class="text-center mt-3">
                                            <small class="text-muted">{{ _lang('Showing top 10 contributors. Total members: ') }}{{ $groupSummary['total_members'] }}</small>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="row">
                    <div class="col-lg-12">
                        <a href="{{ route('customer.vsla.cycles.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to VSLA Cycles') }}
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
