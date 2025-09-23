@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('vsla.cycles.index') }}">{{ _lang('VSLA Cycles') }}</a></li>
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
                        <h4 class="card-title">{{ _lang('Cycle Overview') }} - {{ $cycle->cycle_name }}</h4>
                        <p class="text-muted mb-0">
                            {{ _lang('Duration:') }} {{ $cycle->start_date->format('M d, Y') }} 
                            {{ _lang('to') }} {{ $cycle->end_date ? $cycle->end_date->format('M d, Y') : 'Ongoing' }}
                            ({{ $cycle->getFormattedDuration() }})
                        </p>
                    </div>
                    <div>
                        <span class="badge badge-{{ $cycle->status == 'active' ? 'success' : ($cycle->status == 'completed' ? 'primary' : 'warning') }} badge-lg mr-2">
                            {{ ucfirst(str_replace('_', ' ', $cycle->status)) }}
                        </span>
                        @if($cycle->status === 'active')
                        <button class="btn btn-sm btn-warning" onclick="updateTotals({{ $cycle->id }})">
                            <i class="fas fa-sync"></i> {{ _lang('Update Totals') }}
                        </button>
                        @if($cycle->isEligibleForShareOut())
                        <button class="btn btn-sm btn-danger ml-1" onclick="endCycle({{ $cycle->id }})">
                            <i class="fas fa-stop"></i> {{ _lang('End Cycle') }}
                        </button>
                        @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Financial Integrity Status -->
                @if(!empty($financialErrors))
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="alert alert-danger">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> {{ _lang('Financial Integrity Issues') }}</h5>
                            <ul class="mb-0">
                                @foreach($financialErrors as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Key Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Cycle Statistics') }}</h5>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Total Members') }}</span>
                                        <span class="info-box-number">{{ number_format($stats['total_members']) }}</span>
                                    </div>
                                    <div class="info-box-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Total Shares') }}</span>
                                        <span class="info-box-number">{{ number_format($stats['total_shares_sold']) }}</span>
                                    </div>
                                    <div class="info-box-icon bg-success">
                                        <i class="fas fa-chart-pie"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Total Fund') }}</span>
                                        <span class="info-box-number">{{ number_format($cycle->total_available_for_shareout, 0) }}</span>
                                    </div>
                                    <div class="info-box-icon bg-warning">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Loans Issued') }}</span>
                                        <span class="info-box-number">{{ number_format($stats['total_loans_issued']) }}</span>
                                    </div>
                                    <div class="info-box-icon bg-info">
                                        <i class="fas fa-hand-holding-usd"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Transactions') }}</span>
                                        <span class="info-box-number">{{ number_format($stats['total_transactions']) }}</span>
                                    </div>
                                    <div class="info-box-icon bg-secondary">
                                        <i class="fas fa-list"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box">
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ _lang('Share-outs') }}</span>
                                        <span class="info-box-number">{{ number_format($stats['shareouts_processed']) }}</span>
                                    </div>
                                    <div class="info-box-icon bg-dark">
                                        <i class="fas fa-share-alt"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Financial Summary') }}</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <i class="fas fa-piggy-bank fa-3x text-primary mb-3"></i>
                                        <h6>{{ _lang('Share Contributions') }}</h6>
                                        <h4 class="text-primary" id="total-shares-contributed">{{ number_format($cycle->total_shares_contributed, 2) }}</h4>
                                        <small class="text-muted">{{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-info">
                                    <div class="card-body text-center">
                                        <i class="fas fa-heart fa-3x text-info mb-3"></i>
                                        <h6>{{ _lang('Welfare Fund') }}</h6>
                                        <h4 class="text-info" id="total-welfare-contributed">{{ number_format($cycle->total_welfare_contributed, 2) }}</h4>
                                        <small class="text-muted">{{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-success">
                                    <div class="card-body text-center">
                                        <i class="fas fa-percentage fa-3x text-success mb-3"></i>
                                        <h6>{{ _lang('Interest Earned') }}</h6>
                                        <h4 class="text-success" id="total-interest-earned">{{ number_format($cycle->total_loan_interest_earned, 2) }}</h4>
                                        <small class="text-muted">{{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-warning">
                                    <div class="card-body text-center">
                                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                                        <h6>{{ _lang('Penalties Collected') }}</h6>
                                        <h4 class="text-warning" id="total-penalties-collected">{{ number_format($cycle->total_penalties_collected, 2) }}</h4>
                                        <small class="text-muted">{{ get_base_currency() }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Summary -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Transaction Summary by Type') }}</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>{{ _lang('Transaction Type') }}</th>
                                                <th class="text-center">{{ _lang('Count') }}</th>
                                                <th class="text-right">{{ _lang('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-chart-pie text-primary"></i> {{ _lang('Share Purchases') }}</td>
                                                <td class="text-center">{{ number_format($transactionSummary['share_purchases']['count']) }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['share_purchases']['amount'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-heart text-info"></i> {{ _lang('Welfare Contributions') }}</td>
                                                <td class="text-center">{{ number_format($transactionSummary['welfare_contributions']['count']) }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['welfare_contributions']['amount'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-exclamation-triangle text-warning"></i> {{ _lang('Penalties') }}</td>
                                                <td class="text-center">{{ number_format($transactionSummary['penalties']['count']) }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['penalties']['amount'], 2) }} {{ get_base_currency() }}</td>
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
                                                <th class="text-center">{{ _lang('Count') }}</th>
                                                <th class="text-right">{{ _lang('Amount') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><i class="fas fa-hand-holding-usd text-success"></i> {{ _lang('Loan Issuances') }}</td>
                                                <td class="text-center">{{ number_format($transactionSummary['loan_issuances']['count']) }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['loan_issuances']['amount'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-money-check text-primary"></i> {{ _lang('Loan Repayments') }}</td>
                                                <td class="text-center">{{ number_format($transactionSummary['loan_repayments']['count']) }}</td>
                                                <td class="text-right">{{ number_format($transactionSummary['loan_repayments']['amount'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                            <tr>
                                                <td><i class="fas fa-balance-scale text-danger"></i> {{ _lang('Outstanding Loans') }}</td>
                                                <td class="text-center">-</td>
                                                <td class="text-right">{{ number_format($stats['outstanding_loans'], 2) }} {{ get_base_currency() }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participating Members -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Participating Members') }} ({{ count($participatingMembers) }})</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ _lang('Member') }}</th>
                                        <th class="text-center">{{ _lang('Shares') }}</th>
                                        <th class="text-right">{{ _lang('Share Amount') }}</th>
                                        <th class="text-right">{{ _lang('Welfare') }}</th>
                                        <th class="text-right">{{ _lang('Loans Taken') }}</th>
                                        <th class="text-right">{{ _lang('Loans Repaid') }}</th>
                                        <th class="text-right">{{ _lang('Expected Payout') }}</th>
                                        <th class="text-center">{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($participatingMembers as $memberData)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <strong>{{ $memberData['member']->first_name }} {{ $memberData['member']->last_name }}</strong><br>
                                                    <small class="text-muted">{{ $memberData['member']->member_no }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-primary">{{ number_format($memberData['shares_purchased']) }}</span>
                                        </td>
                                        <td class="text-right">{{ number_format($memberData['share_amount_paid'], 2) }}</td>
                                        <td class="text-right">{{ number_format($memberData['welfare_contributed'], 2) }}</td>
                                        <td class="text-right">{{ number_format($memberData['loans_taken'], 2) }}</td>
                                        <td class="text-right">{{ number_format($memberData['loans_repaid'], 2) }}</td>
                                        <td class="text-right">
                                            <strong class="text-success">{{ number_format($memberData['expected_payout'], 2) }}</strong>
                                        </td>
                                        <td class="text-center">
                                            @if($memberData['shareout'])
                                            <span class="badge badge-success">{{ _lang('Paid Out') }}</span><br>
                                            <small class="text-muted">{{ number_format($memberData['shareout']->net_payout, 2) }}</small>
                                            @else
                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">{{ _lang('No participating members found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <h5 class="mb-3">{{ _lang('Recent Transactions') }} ({{ _lang('Last 10') }})</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ _lang('Date') }}</th>
                                        <th>{{ _lang('Member') }}</th>
                                        <th>{{ _lang('Type') }}</th>
                                        <th class="text-right">{{ _lang('Amount') }}</th>
                                        <th class="text-center">{{ _lang('Shares') }}</th>
                                        <th>{{ _lang('Created By') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentTransactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                                        <td>{{ $transaction->member->first_name }} {{ $transaction->member->last_name }}</td>
                                        <td>
                                            <span class="badge badge-{{ $transaction->transaction_type == 'share_purchase' ? 'primary' : ($transaction->transaction_type == 'loan_issuance' ? 'success' : ($transaction->transaction_type == 'loan_repayment' ? 'info' : 'warning')) }}">
                                                {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                                            </span>
                                        </td>
                                        <td class="text-right">{{ number_format($transaction->amount, 2) }}</td>
                                        <td class="text-center">{{ $transaction->shares ? number_format($transaction->shares) : '-' }}</td>
                                        <td>{{ $transaction->createdUser->name ?? 'System' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ _lang('No recent transactions found') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if(count($recentTransactions) > 0)
                        <div class="text-center">
                            <a href="{{ route('vsla.transactions.history') }}?cycle_id={{ $cycle->id }}" class="btn btn-outline-primary">
                                {{ _lang('View All Transactions') }}
                            </a>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="row">
                    <div class="col-lg-12">
                        <a href="{{ route('vsla.cycles.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Cycles') }}
                        </a>
                        @if($cycle->status === 'active' && $cycle->isEligibleForShareOut() && empty($financialErrors))
                        <a href="{{ route('vsla.cycles.create') }}?cycle_id={{ $cycle->id }}" class="btn btn-warning ml-2">
                            <i class="fas fa-share-alt"></i> {{ _lang('Start Share-out Process') }}
                        </a>
                        @endif
                        <button class="btn btn-info ml-2" onclick="window.print()">
                            <i class="fas fa-print"></i> {{ _lang('Print Report') }}
                        </button>
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

@section('js-script')
<script>
function updateTotals(cycleId) {
    if (confirm('{{ _lang("This will recalculate all cycle totals. Continue?") }}')) {
        $.ajax({
            url: "{{ route('vsla.cycles.update_totals', '') }}/" + cycleId,
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.result === 'success') {
                    $('#total-shares-contributed').text(response.data.total_shares_contributed);
                    $('#total-welfare-contributed').text(response.data.total_welfare_contributed);
                    $('#total-penalties-collected').text(response.data.total_penalties_collected);
                    $('#total-interest-earned').text(response.data.total_loan_interest_earned);
                    
                    toastr.success(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('{{ _lang("An error occurred") }}');
            }
        });
    }
}

function endCycle(cycleId) {
    if (confirm('{{ _lang("This will end the cycle and prepare it for share-out. This action cannot be undone. Continue?") }}')) {
        $.ajax({
            url: "{{ route('vsla.cycles.end_cycle', '') }}/" + cycleId,
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.result === 'success') {
                    toastr.success(response.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    toastr.error(response.message);
                    if (response.errors) {
                        response.errors.forEach(function(error) {
                            toastr.error(error);
                        });
                    }
                }
            },
            error: function() {
                toastr.error('{{ _lang("An error occurred") }}');
            }
        });
    }
}
</script>
@endsection
