@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title">{{ _lang('Payroll Management') }}</h4>
                    <a href="{{ route('payroll.periods.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> {{ _lang('Create Payroll Period') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">{{ $stats['total_periods'] }}</h4>
                                        <p class="mb-0">{{ _lang('Total Periods') }}</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-alt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">{{ $stats['active_periods'] }}</h4>
                                        <p class="mb-0">{{ _lang('Active Periods') }}</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">{{ $stats['total_employees'] }}</h4>
                                        <p class="mb-0">{{ _lang('Total Employees') }}</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0">{{ number_format($stats['total_net_pay'], 2) }}</h4>
                                        <p class="mb-0">{{ _lang('Total Net Pay') }}</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payroll Periods Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Period Name') }}</th>
                                <th>{{ _lang('Start Date') }}</th>
                                <th>{{ _lang('End Date') }}</th>
                                <th>{{ _lang('Pay Date') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Employees') }}</th>
                                <th>{{ _lang('Total Net Pay') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payrollPeriods as $period)
                            <tr>
                                <td>
                                    <strong>{{ $period->period_name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ ucfirst($period->period_type) }}</small>
                                </td>
                                <td>{{ $period->start_date->format('M d, Y') }}</td>
                                <td>{{ $period->end_date->format('M d, Y') }}</td>
                                <td>{{ $period->pay_date ? $period->pay_date->format('M d, Y') : '-' }}</td>
                                <td>
                                    @if($period->status === 'draft')
                                        <span class="badge bg-secondary">{{ _lang('Draft') }}</span>
                                    @elseif($period->status === 'processing')
                                        <span class="badge bg-warning">{{ _lang('Processing') }}</span>
                                    @elseif($period->status === 'completed')
                                        <span class="badge bg-success">{{ _lang('Completed') }}</span>
                                    @elseif($period->status === 'cancelled')
                                        <span class="badge bg-danger">{{ _lang('Cancelled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ $period->total_employees }}</span>
                                </td>
                                <td>
                                    <strong>{{ number_format($period->total_net_pay, 2) }}</strong>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('payroll.periods.show', $period->id) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        @if($period->canBeCancelled())
                                        <a href="{{ route('payroll.periods.edit', $period->id) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endif
                                        
                                        @if($period->canBeProcessed())
                                        <form method="POST" action="{{ route('payroll.periods.process', $period->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('{{ _lang('Are you sure you want to process this payroll period?') }}')">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </form>
                                        @endif
                                        
                                        @if($period->isProcessing())
                                        <form method="POST" action="{{ route('payroll.periods.complete', $period->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('{{ _lang('Are you sure you want to complete this payroll period?') }}')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        @endif
                                        
                                        @if($period->canBeCancelled())
                                        <form method="POST" action="{{ route('payroll.periods.cancel', $period->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('{{ _lang('Are you sure you want to cancel this payroll period?') }}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="py-4">
                                        <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                        <h5>{{ _lang('No Payroll Periods Found') }}</h5>
                                        <p class="text-muted">{{ _lang('Create your first payroll period to get started.') }}</p>
                                        <a href="{{ route('payroll.periods.create') }}" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> {{ _lang('Create Payroll Period') }}
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($payrollPeriods->hasPages())
                <div class="d-flex justify-content-center">
                    {{ $payrollPeriods->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ _lang('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="{{ route('payroll.employees.index') }}" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-users"></i> {{ _lang('Manage Employees') }}
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('payroll.deductions.index') }}" class="btn btn-outline-secondary btn-block">
                            <i class="fas fa-minus-circle"></i> {{ _lang('Manage Deductions') }}
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('payroll.benefits.index') }}" class="btn btn-outline-success btn-block">
                            <i class="fas fa-plus-circle"></i> {{ _lang('Manage Benefits') }}
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="{{ route('payroll.periods.create') }}" class="btn btn-outline-info btn-block">
                            <i class="fas fa-calendar-plus"></i> {{ _lang('Create Period') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Add any JavaScript functionality here
});
</script>
@endsection
