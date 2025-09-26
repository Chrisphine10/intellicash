@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title">{{ _lang('Payroll Period Details') }}</h4>
                    <div>
                        @if($payrollPeriod->canBeCancelled())
                        <a href="{{ route('payroll.periods.edit', $payrollPeriod->id) }}" class="btn btn-warning">
                            <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                        </a>
                        @endif
                        
                        @if($payrollPeriod->canBeProcessed())
                        <form method="POST" action="{{ route('payroll.periods.process', $payrollPeriod->id) }}" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success" onclick="return confirm('{{ _lang('Are you sure you want to process this payroll period?') }}')">
                                <i class="fas fa-play"></i> {{ _lang('Process') }}
                            </button>
                        </form>
                        @endif
                        
                        @if($payrollPeriod->isProcessing())
                        <form method="POST" action="{{ route('payroll.periods.complete', $payrollPeriod->id) }}" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-primary" onclick="return confirm('{{ _lang('Are you sure you want to complete this payroll period?') }}')">
                                <i class="fas fa-check"></i> {{ _lang('Complete') }}
                            </button>
                        </form>
                        @endif
                        
                        <a href="{{ route('payroll.periods.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Period Information -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h5>{{ $payrollPeriod->period_name }}</h5>
                        <p class="text-muted mb-0">{{ ucfirst($payrollPeriod->period_type) }} Period</p>
                        <p class="text-muted">
                            {{ $payrollPeriod->start_date->format('M d, Y') }} - {{ $payrollPeriod->end_date->format('M d, Y') }}
                            @if($payrollPeriod->pay_date)
                                | Pay Date: {{ $payrollPeriod->pay_date->format('M d, Y') }}
                            @endif
                        </p>
                        @if($payrollPeriod->notes)
                        <div class="alert alert-info">
                            <strong>{{ _lang('Notes') }}:</strong> {{ $payrollPeriod->notes }}
                        </div>
                        @endif
                    </div>
                    <div class="col-md-4 text-right">
                        <div class="mb-2">
                            <span class="badge {{ $payrollPeriod->status === 'draft' ? 'bg-secondary' : ($payrollPeriod->status === 'processing' ? 'bg-warning' : ($payrollPeriod->status === 'completed' ? 'bg-success' : 'bg-danger')) }} fs-6">
                                {{ ucfirst($payrollPeriod->status) }}
                            </span>
                        </div>
                        <p class="text-muted mb-0">
                            <small>{{ _lang('Created by') }}: {{ $payrollPeriod->createdBy->name ?? 'System' }}</small>
                        </p>
                        @if($payrollPeriod->processedBy)
                        <p class="text-muted mb-0">
                            <small>{{ _lang('Processed by') }}: {{ $payrollPeriod->processedBy->name }}</small>
                        </p>
                        @endif
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ $summary['total_employees'] }}</h3>
                                <p class="mb-0">{{ _lang('Employees') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ number_format($summary['total_gross_pay'], 2) }}</h3>
                                <p class="mb-0">{{ _lang('Gross Pay') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ number_format($summary['total_deductions'], 2) }}</h3>
                                <p class="mb-0">{{ _lang('Deductions') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3 class="mb-0">{{ number_format($summary['total_net_pay'], 2) }}</h3>
                                <p class="mb-0">{{ _lang('Net Pay') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payroll Items Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Employee') }}</th>
                                <th>{{ _lang('Basic Salary') }}</th>
                                <th>{{ _lang('Overtime') }}</th>
                                <th>{{ _lang('Bonus') }}</th>
                                <th>{{ _lang('Gross Pay') }}</th>
                                <th>{{ _lang('Deductions') }}</th>
                                <th>{{ _lang('Benefits') }}</th>
                                <th>{{ _lang('Net Pay') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payrollItems as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->employee->full_name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $item->employee->job_title }}</small>
                                </td>
                                <td>{{ number_format($item->basic_salary, 2) }}</td>
                                <td>{{ number_format($item->overtime_pay, 2) }}</td>
                                <td>{{ number_format($item->bonus, 2) }}</td>
                                <td><strong>{{ number_format($item->gross_pay, 2) }}</strong></td>
                                <td>{{ number_format($item->total_deductions, 2) }}</td>
                                <td>{{ number_format($item->total_benefits, 2) }}</td>
                                <td><strong>{{ number_format($item->net_pay, 2) }}</strong></td>
                                <td>
                                    @if($item->status === 'draft')
                                        <span class="badge bg-secondary">{{ _lang('Draft') }}</span>
                                    @elseif($item->status === 'approved')
                                        <span class="badge bg-success">{{ _lang('Approved') }}</span>
                                    @elseif($item->status === 'paid')
                                        <span class="badge bg-primary">{{ _lang('Paid') }}</span>
                                    @elseif($item->status === 'cancelled')
                                        <span class="badge bg-danger">{{ _lang('Cancelled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        @if($item->canBeApproved())
                                        <form method="POST" action="{{ route('payroll.items.approve', $item->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('{{ _lang('Are you sure you want to approve this payroll item?') }}')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        @endif
                                        
                                        @if($item->canBePaid())
                                        <form method="POST" action="{{ route('payroll.items.mark-paid', $item->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('{{ _lang('Are you sure you want to mark this as paid?') }}')">
                                                <i class="fas fa-dollar-sign"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center">
                                    <div class="py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>{{ _lang('No Employees Added') }}</h5>
                                        <p class="text-muted">{{ _lang('Add employees to this payroll period to get started.') }}</p>
                                        @if($payrollPeriod->canBeCancelled())
                                        <a href="{{ route('payroll.periods.edit', $payrollPeriod->id) }}" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> {{ _lang('Add Employees') }}
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Processing History -->
                @if($payrollPeriod->getProcessingHistory())
                <div class="mt-4">
                    <h5>{{ _lang('Processing History') }}</h5>
                    <div class="timeline">
                        @foreach($payrollPeriod->getProcessingHistory() as $log)
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">{{ ucfirst($log['action']) }}</h6>
                                <p class="timeline-text">{{ $log['details'] ?? '' }}</p>
                                <small class="text-muted">
                                    {{ \Carbon\Carbon::parse($log['timestamp'])->format('M d, Y H:i') }} - 
                                    {{ $log['user_name'] }}
                                </small>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -8px;
    top: 0;
    width: 16px;
    height: 16px;
    background-color: #007bff;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #007bff;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
}

.timeline-text {
    margin-bottom: 5px;
    color: #6c757d;
}
</style>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Add any JavaScript functionality here
});
</script>
@endsection
