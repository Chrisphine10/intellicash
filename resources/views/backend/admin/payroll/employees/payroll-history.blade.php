@extends('layouts.app')

@section('title', _lang('Employee Payroll History'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Payroll History') }} - {{ $employee->first_name }} {{ $employee->last_name }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Employee') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($payrollHistory->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Pay Period') }}</th>
                                    <th>{{ _lang('Basic Salary') }}</th>
                                    <th>{{ _lang('Benefits') }}</th>
                                    <th>{{ _lang('Deductions') }}</th>
                                    <th>{{ _lang('Net Pay') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payrollHistory as $item)
                                <tr>
                                    <td>
                                        {{ $item->payrollPeriod->name ?? 'N/A' }}<br>
                                        <small class="text-muted">
                                            {{ $item->payrollPeriod->start_date ?? 'N/A' }} - {{ $item->payrollPeriod->end_date ?? 'N/A' }}
                                        </small>
                                    </td>
                                    <td>{{ decimalPlace($item->basic_salary, currency($item->currency ?? 'USD')) }}</td>
                                    <td>{{ decimalPlace($item->total_benefits, currency($item->currency ?? 'USD')) }}</td>
                                    <td>{{ decimalPlace($item->total_deductions, currency($item->currency ?? 'USD')) }}</td>
                                    <td>
                                        <strong>{{ decimalPlace($item->net_pay, currency($item->currency ?? 'USD')) }}</strong>
                                    </td>
                                    <td>
                                        @if($item->status == 'paid')
                                            <span class="badge bg-success">{{ _lang('Paid') }}</span>
                                        @elseif($item->status == 'pending')
                                            <span class="badge bg-warning">{{ _lang('Pending') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ _lang('Draft') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-info btn-sm" title="{{ _lang('View Details') }}">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        {{ $payrollHistory->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ _lang('No Payroll History Found') }}</h5>
                        <p class="text-muted">{{ _lang('This employee has no payroll history yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
