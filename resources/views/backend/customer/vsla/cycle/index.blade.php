@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('My VSLA Cycle Reports') }}</h4>
                <p class="text-muted mb-0">{{ _lang('View your participation and returns for all VSLA cycles') }}</p>
            </div>
            <div class="card-body">
                @if(!$hasVslaActivity)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>{{ _lang('No VSLA Activity Found') }}</strong><br>
                        {{ _lang('You do not have any VSLA transactions recorded yet. To participate in VSLA cycles, you need to make share purchases and other VSLA contributions during active cycles.') }}
                    </div>
                @endif

                @if($cycles->count() > 0)
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>{{ _lang('About VSLA Cycles:') }}</strong> 
                        {{ _lang('VSLA cycles are periods where members contribute shares, welfare, and participate in group savings. At the end of each cycle, funds are distributed based on your participation and contributions.') }}
                    </div>

                    <div class="row">
                        @foreach($cycles as $cycle)
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                {{ $cycle->cycle_name }}
                                            </div>
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto">
                                                    <div class="h6 mb-0 mr-3 font-weight-bold text-gray-800">
                                                        <span class="badge badge-{{ $cycle->status == 'active' ? 'success' : ($cycle->status == 'completed' ? 'primary' : 'warning') }}">
                                                            {{ ucfirst($cycle->status) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> 
                                                    {{ $cycle->start_date->format('M d, Y') }} - 
                                                    {{ $cycle->end_date ? $cycle->end_date->format('M d, Y') : 'Ongoing' }}
                                                </small>
                                            </div>
                                            @if($cycle->total_available_for_shareout > 0)
                                            <div class="mt-2">
                                                <small class="text-success font-weight-bold">
                                                    <i class="fas fa-coins"></i> 
                                                    {{ _lang('Total Fund:') }} {{ number_format($cycle->total_available_for_shareout, 2) }} {{ get_base_currency() }}
                                                </small>
                                            </div>
                                            @endif
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="{{ route('customer.vsla.cycle.show', $cycle->id) }}" class="btn btn-primary btn-sm btn-block">
                                            <i class="fas fa-eye"></i> {{ _lang('View Cycle Details') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($cycles->count() > 6)
                    <div class="text-center mt-4">
                        <div class="alert alert-light">
                            <i class="fas fa-info-circle"></i>
                            {{ _lang('Showing recent cycles. Contact your administrator for historical cycle information.') }}
                        </div>
                    </div>
                    @endif

                @else
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-3x mb-3 text-muted"></i>
                        <h5>{{ _lang('No VSLA Cycles Found') }}</h5>
                        <p class="mb-0">{{ _lang('There are currently no VSLA cycles available. Please contact your administrator for more information about VSLA cycle management.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ _lang('Understanding VSLA Cycles') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-piggy-bank fa-3x text-primary mb-2"></i>
                            <h6>{{ _lang('Share Contributions') }}</h6>
                            <p class="text-muted small">{{ _lang('Your share purchases during the cycle determine your ownership percentage.') }}</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-percentage fa-3x text-success mb-2"></i>
                            <h6>{{ _lang('Interest Earnings') }}</h6>
                            <p class="text-muted small">{{ _lang('Interest earned from loans is distributed proportionally to share ownership.') }}</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-heart fa-3x text-info mb-2"></i>
                            <h6>{{ _lang('Welfare Returns') }}</h6>
                            <p class="text-muted small">{{ _lang('Welfare contributions may be returned based on group decisions.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css-script')
<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.card-body {
    transition: transform 0.2s;
}

.card:hover .card-body {
    transform: translateY(-2px);
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}
</style>
@endsection
