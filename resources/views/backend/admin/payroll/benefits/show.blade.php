@extends('layouts.app')

@section('title', _lang('Benefit Details'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Benefit Details') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('payroll.benefits.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Benefits') }}
                    </a>
                    <a href="{{ route('payroll.benefits.edit', $benefit->id) }}" class="btn btn-primary btn-sm">
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
                                <td>{{ $benefit->name }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Code') }}</th>
                                <td><span class="badge badge-info">{{ $benefit->code }}</span></td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Description') }}</th>
                                <td>{{ $benefit->description ?: _lang('No description') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Type') }}</th>
                                <td>
                                    <span class="badge badge-{{ $benefit->type == 'percentage' ? 'success' : ($benefit->type == 'fixed_amount' ? 'primary' : 'warning') }}">
                                        {{ ucfirst(str_replace('_', ' ', $benefit->type)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Rate/Amount') }}</th>
                                <td>{{ $benefit->getFormattedRate() }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Category') }}</th>
                                <td>{{ $benefit->category ? ucfirst(str_replace('_', ' ', $benefit->category)) : _lang('Not specified') }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">{{ _lang('Minimum Amount') }}</th>
                                <td>{{ $benefit->minimum_amount ? 'KSh ' . number_format($benefit->minimum_amount, 2) : _lang('Not set') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Maximum Amount') }}</th>
                                <td>{{ $benefit->maximum_amount ? 'KSh ' . number_format($benefit->maximum_amount, 2) : _lang('Not set') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Employer Paid') }}</th>
                                <td>
                                    <span class="badge badge-{{ $benefit->is_employer_paid ? 'success' : 'secondary' }}">
                                        {{ $benefit->is_employer_paid ? _lang('Yes') : _lang('No') }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Status') }}</th>
                                <td>
                                    <span class="badge badge-{{ $benefit->is_active ? 'success' : 'danger' }}">
                                        {{ $benefit->is_active ? _lang('Active') : _lang('Inactive') }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Effective Date') }}</th>
                                <td>{{ $benefit->effective_date ? date('M d, Y', strtotime($benefit->effective_date)) : _lang('Not set') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Expiry Date') }}</th>
                                <td>{{ $benefit->expiry_date ? date('M d, Y', strtotime($benefit->expiry_date)) : _lang('Not set') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">{{ _lang('Created By') }}</th>
                                <td>{{ $benefit->createdBy ? $benefit->createdBy->name : _lang('Unknown') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Created At') }}</th>
                                <td>{{ date('M d, Y H:i', strtotime($benefit->created_at)) }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Updated At') }}</th>
                                <td>{{ date('M d, Y H:i', strtotime($benefit->updated_at)) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($benefit->employeeBenefits && $benefit->employeeBenefits->count() > 0)
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
                                    @foreach($benefit->employeeBenefits as $employeeBenefit)
                                    <tr>
                                        <td>
                                            <a href="{{ route('payroll.employees.show', $employeeBenefit->employee->id) }}">
                                                {{ $employeeBenefit->employee->first_name }} {{ $employeeBenefit->employee->last_name }}
                                            </a>
                                        </td>
                                        <td>{{ $employeeBenefit->getFormattedAmount() }}</td>
                                        <td>
                                            <span class="badge badge-{{ $employeeBenefit->is_active ? 'success' : 'danger' }}">
                                                {{ $employeeBenefit->is_active ? _lang('Active') : _lang('Inactive') }}
                                            </span>
                                        </td>
                                        <td>{{ date('M d, Y', strtotime($employeeBenefit->created_at)) }}</td>
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
