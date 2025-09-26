@extends('layouts.app')

@section('title', _lang('Deduction Details'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Deduction Details') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('payroll.deductions.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Deductions') }}
                    </a>
                    <a href="{{ route('payroll.deductions.edit', $deduction->id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">{{ _lang('Name') }}</th>
                                <td>{{ $deduction->name }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Code') }}</th>
                                <td><span class="badge badge-info">{{ $deduction->code }}</span></td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Description') }}</th>
                                <td>{{ $deduction->description ?: _lang('No description') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Type') }}</th>
                                <td>
                                    <span class="badge badge-{{ $deduction->type == 'percentage' ? 'success' : ($deduction->type == 'fixed_amount' ? 'primary' : 'warning') }}">
                                        {{ ucfirst(str_replace('_', ' ', $deduction->type)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Rate/Amount') }}</th>
                                <td>{{ $deduction->getFormattedRate() }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Tax Category') }}</th>
                                <td>{{ $deduction->tax_category ? ucfirst(str_replace('_', ' ', $deduction->tax_category)) : _lang('Not specified') }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">{{ _lang('Minimum Amount') }}</th>
                                <td>{{ $deduction->minimum_amount ? 'KSh ' . number_format($deduction->minimum_amount, 2) : _lang('Not set') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Maximum Amount') }}</th>
                                <td>{{ $deduction->maximum_amount ? 'KSh ' . number_format($deduction->maximum_amount, 2) : _lang('Not set') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Mandatory') }}</th>
                                <td>
                                    <span class="badge badge-{{ $deduction->is_mandatory ? 'danger' : 'secondary' }}">
                                        {{ $deduction->is_mandatory ? _lang('Yes') : _lang('No') }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Status') }}</th>
                                <td>
                                    <span class="badge badge-{{ $deduction->is_active ? 'success' : 'danger' }}">
                                        {{ $deduction->is_active ? _lang('Active') : _lang('Inactive') }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Created By') }}</th>
                                <td>{{ $deduction->createdBy ? $deduction->createdBy->name : _lang('Unknown') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Created At') }}</th>
                                <td>{{ date('M d, Y H:i', strtotime($deduction->created_at)) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($deduction->tiered_rates)
                <div class="row mt-4">
                    <div class="col-12">
                        <h5>{{ _lang('Tiered Rates') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Min Amount') }}</th>
                                        <th>{{ _lang('Max Amount') }}</th>
                                        <th>{{ _lang('Rate (%)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deduction->tiered_rates as $tier)
                                    <tr>
                                        <td>KSh {{ number_format($tier['min'], 2) }}</td>
                                        <td>{{ $tier['max'] == PHP_FLOAT_MAX ? _lang('No limit') : 'KSh ' . number_format($tier['max'], 2) }}</td>
                                        <td>{{ $tier['rate'] }}%</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                @if($deduction->employeeDeductions && $deduction->employeeDeductions->count() > 0)
                <div class="row mt-4">
                    <div class="col-12">
                        <h5>{{ _lang('Assigned Employees') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Employee') }}</th>
                                        <th>{{ _lang('Amount') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Assigned Date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deduction->employeeDeductions as $employeeDeduction)
                                    <tr>
                                        <td>
                                            <a href="{{ route('payroll.employees.show', $employeeDeduction->employee->id) }}">
                                                {{ $employeeDeduction->employee->first_name }} {{ $employeeDeduction->employee->last_name }}
                                            </a>
                                        </td>
                                        <td>{{ $employeeDeduction->getFormattedAmount() }}</td>
                                        <td>
                                            <span class="badge badge-{{ $employeeDeduction->is_active ? 'success' : 'danger' }}">
                                                {{ $employeeDeduction->is_active ? _lang('Active') : _lang('Inactive') }}
                                            </span>
                                        </td>
                                        <td>{{ date('M d, Y', strtotime($employeeDeduction->created_at)) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
