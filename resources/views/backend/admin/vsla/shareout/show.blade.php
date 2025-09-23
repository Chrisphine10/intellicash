@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <!-- Cycle Information -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('VSLA Cycle Details') }} - {{ $cycle->cycle_name }}</span>
                <div class="ml-auto">
                    @php $phase = $cycle->getCurrentPhase(); @endphp
                    @if($phase == 'active')
                        <span class="badge badge-success badge-lg">{{ _lang('Active Phase') }}</span>
                    @elseif($phase == 'ready_for_shareout')
                        <span class="badge badge-info badge-lg">{{ _lang('Ready for Share-Out') }}</span>
                    @elseif($phase == 'share_out')
                        <span class="badge badge-warning badge-lg">{{ _lang('Share-Out Phase') }}</span>
                    @elseif($phase == 'completed')
                        <span class="badge badge-primary badge-lg">{{ _lang('Completed') }}</span>
                    @else
                        <span class="badge badge-secondary badge-lg">{{ _lang('Archived') }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <!-- Cycle Phase Information -->
                <!-- Cycle Progress -->
                <div class="mb-4">
                    <h5>{{ _lang('Cycle Progress') }}</h5>
                    <div class="progress-steps">
                        @php 
                            $phase = $cycle->getCurrentPhase();
                            $phases = ['active', 'ready_for_shareout', 'share_out', 'completed'];
                            $currentIndex = array_search($phase, $phases);
                        @endphp
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="step {{ $currentIndex >= 0 ? 'active' : '' }}">
                                <div class="step-circle {{ $currentIndex >= 0 ? 'bg-success' : 'bg-light' }}">1</div>
                                <small>{{ _lang('Active') }}</small>
                            </div>
                            <div class="step-line {{ $currentIndex >= 1 ? 'active' : '' }}"></div>
                            <div class="step {{ $currentIndex >= 1 ? 'active' : '' }}">
                                <div class="step-circle {{ $currentIndex >= 1 ? 'bg-info' : 'bg-light' }}">2</div>
                                <small>{{ _lang('Ready') }}</small>
                            </div>
                            <div class="step-line {{ $currentIndex >= 2 ? 'active' : '' }}"></div>
                            <div class="step {{ $currentIndex >= 2 ? 'active' : '' }}">
                                <div class="step-circle {{ $currentIndex >= 2 ? 'bg-warning' : 'bg-light' }}">3</div>
                                <small>{{ _lang('Share-Out') }}</small>
                            </div>
                            <div class="step-line {{ $currentIndex >= 3 ? 'active' : '' }}"></div>
                            <div class="step {{ $currentIndex >= 3 ? 'active' : '' }}">
                                <div class="step-circle {{ $currentIndex >= 3 ? 'bg-primary' : 'bg-light' }}">4</div>
                                <small>{{ _lang('Completed') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <strong>{{ _lang('Current Phase') }}:</strong> {{ $cycle->getPhaseDescription() }}
                    </div>
                </div>

                <style>
                    .step {
                        text-align: center;
                        flex: 1;
                    }
                    .step-circle {
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 5px;
                        color: white;
                        font-weight: bold;
                    }
                    .step-line {
                        height: 2px;
                        background: #dee2e6;
                        flex: 1;
                        margin: 20px 10px 0;
                    }
                    .step-line.active {
                        background: #28a745;
                    }
                </style>
                <div class="row">
                    <div class="col-md-3">
                        <strong>{{ _lang('Period:') }}</strong><br>
                        {{ $cycle->start_date->format('M d, Y') }} - {{ $cycle->end_date->format('M d, Y') }}<br>
                        <small class="text-muted">{{ $cycle->getFormattedDuration() }}</small>
                    </div>
                    <div class="col-md-3">
                        <strong>{{ _lang('Created by:') }}</strong><br>
                        {{ $cycle->createdUser->name ?? 'N/A' }}<br>
                        <small class="text-muted">{{ $cycle->created_at->format('M d, Y H:i') }}</small>
                    </div>
                    <div class="col-md-3">
                        <strong>{{ _lang('Participating Members:') }}</strong><br>
                        {{ $participatingMembers->count() }}
                    </div>
                </div>
                
                @if($cycle->notes)
                    <div class="row mt-3">
                        <div class="col-12">
                            <strong>{{ _lang('Notes:') }}</strong><br>
                            {{ $cycle->notes }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Cycle Statistics -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Cycle Statistics') }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-primary">{{ $cycleStats['duration_days'] }}</h4>
                            <small>{{ _lang('Days Duration') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-success">{{ $cycleStats['total_transactions'] }}</h4>
                            <small>{{ _lang('Total Transactions') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-info">{{ $cycleStats['unique_members'] }}</h4>
                            <small>{{ _lang('Active Members') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-warning">{{ $cycleStats['total_meetings'] }}</h4>
                            <small>{{ _lang('Meetings Held') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-secondary">{{ currency($cycleStats['average_transaction_amount']) }}</h4>
                            <small>{{ _lang('Avg Transaction') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-dark">{{ number_format($cycleStats['transaction_frequency'], 1) }}</h4>
                            <small>{{ _lang('Trans/Week') }}</small>
                        </div>
                    </div>
                </div>
                
                @if($cycleStats['most_active_member'])
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="alert alert-light">
                            <i class="fas fa-star text-warning"></i>
                            <strong>{{ _lang('Most Active Member:') }}</strong>
                            {{ $cycleStats['most_active_member']['member']->first_name }} {{ $cycleStats['most_active_member']['member']->last_name }}
                            ({{ $cycleStats['most_active_member']['transaction_count'] }} transactions, {{ currency($cycleStats['most_active_member']['total_amount']) }})
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Transaction Breakdown -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Transaction Breakdown') }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($transactionBreakdown as $type => $data)
                    <div class="col-md-3 mb-3">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            {{ _lang(str_replace('_', ' ', $type)) }}
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            {{ $data->count }} {{ _lang('transactions') }}
                                        </div>
                                        <div class="mt-2">
                                            <strong>{{ currency($data->total_amount) }}</strong>
                                            @if($data->total_shares > 0)
                                            <br><small class="text-muted">{{ $data->total_shares }} {{ _lang('shares') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        @if($type == 'share_purchase')
                                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                        @elseif($type == 'welfare_contribution')
                                            <i class="fas fa-heart fa-2x text-gray-300"></i>
                                        @elseif($type == 'penalty_fine')
                                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                        @elseif($type == 'loan_issuance')
                                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                        @elseif($type == 'loan_repayment')
                                            <i class="fas fa-money-check fa-2x text-gray-300"></i>
                                        @else
                                            <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Financial Summary') }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-primary">{{ currency($cycle->total_shares_contributed) }}</h4>
                            <small>{{ _lang('Total Shares') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-info">{{ currency($cycle->total_welfare_contributed) }}</h4>
                            <small>{{ _lang('Total Welfare') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-warning">{{ currency($cycle->total_penalties_collected) }}</h4>
                            <small>{{ _lang('Penalties') }}</small>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="text-center">
                            <h4 class="text-success">{{ currency($cycle->total_loan_interest_earned) }}</h4>
                            <small>{{ _lang('Loan Interest') }}</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-dark font-weight-bold">{{ currency($cycle->total_available_for_shareout) }}</h4>
                            <small>{{ _lang('Available for Share-Out') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
@if($cycle->status == 'active' || $cycle->status == 'share_out_in_progress')
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Actions') }}</span>
            </div>
            <div class="card-body">
                @if($cycle->status == 'active' && $cycle->isEligibleForShareOut())
                    <a href="{{ route('vsla.cycles.calculate', $cycle->id) }}" 
                       class="btn btn-warning"
                       onclick="return confirm('{{ _lang('Are you sure you want to calculate share-out for this cycle? This will analyze all transactions and prepare payout calculations.') }}')">
                        <i class="fas fa-calculator"></i> {{ _lang('Calculate Share-Out') }}
                    </a>
                @elseif($cycle->status == 'active' && !$cycle->isEligibleForShareOut())
                    <div class="alert alert-info">
                        {{ _lang('Share-out can only be calculated after the cycle end date') }} ({{ $cycle->end_date->format('M d, Y') }})
                    </div>
                @endif
                
                @if($cycle->status == 'share_out_in_progress')
                    <a href="{{ route('vsla.cycles.approve', $cycle->id) }}" 
                       class="btn btn-success"
                       onclick="return confirm('{{ _lang('Are you sure you want to approve these share-out calculations?') }}')">
                        <i class="fas fa-check"></i> {{ _lang('Approve Calculations') }}
                    </a>
                    
                    <a href="{{ route('vsla.cycles.process_payout', $cycle->id) }}" 
                       class="btn btn-primary ml-2"
                       onclick="return confirm('{{ _lang('Are you sure you want to process payouts? This will create transactions for all members and complete the cycle. This action cannot be undone.') }}')">
                        <i class="fas fa-money-bill-wave"></i> {{ _lang('Process Payouts') }}
                    </a>
                    
                    <a href="{{ route('vsla.cycles.cancel', $cycle->id) }}" 
                       class="btn btn-outline-danger ml-2"
                       onclick="return confirm('{{ _lang('Are you sure you want to cancel share-out calculations? This will delete all calculations and return the cycle to active status.') }}')">
                        <i class="fas fa-times"></i> {{ _lang('Cancel Share-Out') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Share-Out Details -->
@if($cycle->shareouts->count() > 0)
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('Share-Out Details') }}</span>
                @if($cycle->status == 'completed')
                    <a class="btn btn-light btn-sm ml-auto" href="{{ route('vsla.cycles.export_report', $cycle->id) }}" target="_blank">
                        <i class="fas fa-download"></i> {{ _lang('Export Report') }}
                    </a>
                @endif
            </div>
            <div class="card-body">
                <!-- Validation Summary -->
                @if($cycle->status == 'share_out_in_progress')
                    <div class="alert alert-warning mb-3">
                        <h5><i class="fas fa-exclamation-triangle"></i> {{ _lang('Share-Out Calculations Ready') }}</h5>
                        <p class="mb-0">{{ _lang('Please review the calculations below carefully before approving. Once approved and processed, transactions will be created and member accounts will be updated.') }}</p>
                    </div>
                @elseif($cycle->status == 'completed')
                    <div class="alert alert-success mb-3">
                        <h5><i class="fas fa-check-circle"></i> {{ _lang('Share-Out Completed') }}</h5>
                        <p class="mb-0">{{ _lang('Share-out has been successfully processed on') }} {{ $cycle->share_out_date->format('F d, Y \a\t g:i A') }}. {{ _lang('All member payouts have been distributed.') }}</p>
                    </div>
                @endif

                <!-- Summary Totals -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-primary">{{ currency($cycle->shareouts->sum('total_payout')) }}</h4>
                                <small>{{ _lang('Total Gross Payouts') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-danger">{{ currency($cycle->shareouts->sum('outstanding_loan_balance')) }}</h4>
                                <small>{{ _lang('Total Loan Deductions') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-success">{{ currency($cycle->shareouts->sum('net_payout')) }}</h4>
                                <small>{{ _lang('Total Net Payouts') }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h4 class="text-info">{{ $cycle->shareouts->count() }}</h4>
                                <small>{{ _lang('Total Members') }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ _lang('Member') }}</th>
                                <th>{{ _lang('Shares Contributed') }}</th>
                                <th>{{ _lang('Share %') }}</th>
                                <th>{{ _lang('Welfare Contributed') }}</th>
                                <th>{{ _lang('Profit Share') }}</th>
                                <th>{{ _lang('Total Payout') }}</th>
                                <th>{{ _lang('Outstanding Loans') }}</th>
                                <th>{{ _lang('Net Payout') }}</th>
                                <th>{{ _lang('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cycle->shareouts as $shareout)
                                <tr>
                                    <td>{{ $shareout->member->first_name }} {{ $shareout->member->last_name }}</td>
                                    <td>{{ currency($shareout->total_shares_contributed) }}</td>
                                    <td>{{ $shareout->getFormattedSharePercentage() }}</td>
                                    <td>{{ currency($shareout->total_welfare_contributed) }}</td>
                                    <td>{{ currency($shareout->profit_share) }}</td>
                                    <td>{{ currency($shareout->total_payout) }}</td>
                                    <td>
                                        @if($shareout->outstanding_loan_balance > 0)
                                            <span class="text-danger">{{ currency($shareout->outstanding_loan_balance) }}</span>
                                        @else
                                            <span class="text-muted">{{ currency(0) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ currency($shareout->net_payout) }}</strong>
                                    </td>
                                    <td>
                                        @if($shareout->payout_status == 'calculated')
                                            <span class="badge badge-warning">{{ _lang('Calculated') }}</span>
                                        @elseif($shareout->payout_status == 'approved')
                                            <span class="badge badge-info">{{ _lang('Approved') }}</span>
                                        @elseif($shareout->payout_status == 'paid')
                                            <span class="badge badge-success">{{ _lang('Paid') }}</span>
                                            @if($shareout->paid_at)
                                                <br><small class="text-muted">{{ $shareout->paid_at->format('M d, Y H:i') }}</small>
                                            @endif
                                        @else
                                            <span class="badge badge-secondary">{{ $shareout->payout_status }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-active">
                                <th>{{ _lang('TOTALS') }}</th>
                                <th>{{ currency($cycle->shareouts->sum('total_shares_contributed')) }}</th>
                                <th>100.000%</th>
                                <th>{{ currency($cycle->shareouts->sum('total_welfare_contributed')) }}</th>
                                <th>{{ currency($cycle->shareouts->sum('profit_share')) }}</th>
                                <th>{{ currency($cycle->shareouts->sum('total_payout')) }}</th>
                                <th>{{ currency($cycle->shareouts->sum('outstanding_loan_balance')) }}</th>
                                <th>{{ currency($cycle->shareouts->sum('net_payout')) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@else
<!-- Member Participation Summary -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Member Participation Summary') }}</span>
            </div>
            <div class="card-body">
                @if($memberParticipation->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ _lang('Member') }}</th>
                                    <th class="text-center">{{ _lang('Shares') }}</th>
                                    <th class="text-center">{{ _lang('Share %') }}</th>
                                    <th class="text-right">{{ _lang('Share Amount') }}</th>
                                    <th class="text-right">{{ _lang('Welfare') }}</th>
                                    <th class="text-right">{{ _lang('Penalties') }}</th>
                                    <th class="text-right">{{ _lang('Loans Taken') }}</th>
                                    <th class="text-right">{{ _lang('Loans Repaid') }}</th>
                                    <th class="text-center">{{ _lang('Transactions') }}</th>
                                    <th class="text-right">{{ _lang('Total Activity') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($memberParticipation as $participation)
                                    <tr>
                                        <td>
                                            <strong>{{ $participation['member']->first_name }} {{ $participation['member']->last_name }}</strong><br>
                                            <small class="text-muted">{{ $participation['member']->member_no ?? 'N/A' }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary">{{ number_format($participation['total_shares']) }}</span>
                                        </td>
                                        <td class="text-center">
                                            <strong>{{ number_format($participation['share_percentage'], 1) }}%</strong>
                                        </td>
                                        <td class="text-right">{{ currency($participation['share_amount']) }}</td>
                                        <td class="text-right">{{ currency($participation['welfare_amount']) }}</td>
                                        <td class="text-right">
                                            @if($participation['penalty_amount'] > 0)
                                                <span class="text-danger">{{ currency($participation['penalty_amount']) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($participation['loans_taken'] > 0)
                                                <span class="text-warning">{{ currency($participation['loans_taken']) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            @if($participation['loans_repaid'] > 0)
                                                <span class="text-success">{{ currency($participation['loans_repaid']) }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary">{{ $participation['total_transactions'] }}</span>
                                        </td>
                                        <td class="text-right">
                                            <strong>{{ currency($participation['total_amount']) }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="thead-light">
                                <tr>
                                    <th>{{ _lang('TOTALS') }}</th>
                                    <th class="text-center">{{ number_format($memberParticipation->sum('total_shares')) }}</th>
                                    <th class="text-center">100.0%</th>
                                    <th class="text-right">{{ currency($memberParticipation->sum('share_amount')) }}</th>
                                    <th class="text-right">{{ currency($memberParticipation->sum('welfare_amount')) }}</th>
                                    <th class="text-right">{{ currency($memberParticipation->sum('penalty_amount')) }}</th>
                                    <th class="text-right">{{ currency($memberParticipation->sum('loans_taken')) }}</th>
                                    <th class="text-right">{{ currency($memberParticipation->sum('loans_repaid')) }}</th>
                                    <th class="text-center">{{ $memberParticipation->sum('total_transactions') }}</th>
                                    <th class="text-right"><strong>{{ currency($memberParticipation->sum('total_amount')) }}</strong></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning">
                        {{ _lang('No participating members found for this cycle. Members who made transactions during the cycle period will appear here.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Navigation -->
<div class="row mt-4">
    <div class="col-lg-12">
        <a href="{{ route('vsla.cycles.index') }}" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Cycles') }}
        </a>
    </div>
</div>
@endsection

@section('css-script')
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.card:hover {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25) !important;
    transition: all 0.3s;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.badge {
    font-size: 0.85em;
}

.alert-light {
    border-left: 4px solid #ffc107;
}

.card-body h4 {
    font-weight: 600;
}

.card-body small {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
@endsection
