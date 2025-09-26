@extends('layouts.app')

@section('title', _lang('Employee Details'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Employee Details') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.employees.edit', $employee->id) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                    </a>
                    <a href="{{ route('payroll.employees.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Employees') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Employee Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">{{ _lang('Employee Information') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Employee ID') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->employee_id }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Status') }}</label>
                                            <p class="form-control-plaintext">
                                                @if($employee->is_active)
                                                    <span class="badge bg-success">{{ _lang('Active') }}</span>
                                                @else
                                                    <span class="badge bg-danger">{{ _lang('Inactive') }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('First Name') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->first_name }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Middle Name') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->middle_name ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Last Name') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->last_name }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Email') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->email ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Phone') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->phone ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>{{ _lang('Address') }}</label>
                                    <p class="form-control-plaintext">{{ $employee->address ?? '-' }}</p>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Date of Birth') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->date_of_birth ? \Carbon\Carbon::parse($employee->date_of_birth)->format('M d, Y') : '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Gender') }}</label>
                                            <p class="form-control-plaintext">{{ ucfirst($employee->gender) ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('National ID') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->national_id ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employment Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title">{{ _lang('Employment Information') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Hire Date') }}</label>
                                            <p class="form-control-plaintext">{{ \Carbon\Carbon::parse($employee->hire_date)->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Job Title') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->job_title }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Department') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->department ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Employment Type') }}</label>
                                            <p class="form-control-plaintext">{{ ucfirst(str_replace('_', ' ', $employee->employment_type)) }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Pay Frequency') }}</label>
                                            <p class="form-control-plaintext">{{ ucfirst(str_replace('_', ' ', $employee->pay_frequency)) }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Basic Salary') }}</label>
                                            <p class="form-control-plaintext">{{ number_format($employee->basic_salary, 2) }} {{ $employee->salary_currency }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bank Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title">{{ _lang('Bank Information') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Bank Name') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->bank_name ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Account Number') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->bank_account_number ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{ _lang('Routing Number') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->bank_routing_number ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Tax ID') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->tax_id ?? '-' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{ _lang('Social Security Number') }}</label>
                                            <p class="form-control-plaintext">{{ $employee->social_security_number ?? '-' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-md-4">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">{{ _lang('Quick Actions') }}</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="{{ route('payroll.employees.edit', $employee->id) }}" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> {{ _lang('Edit Employee') }}
                                    </a>
                                    <a href="{{ route('payroll.employees.payroll-history', $employee->id) }}" class="btn btn-info">
                                        <i class="fas fa-history"></i> {{ _lang('Payroll History') }}
                                    </a>
                                    <a href="{{ route('payroll.employees.deductions', $employee->id) }}" class="btn btn-primary">
                                        <i class="fas fa-minus-circle"></i> {{ _lang('Manage Deductions') }}
                                    </a>
                                    <a href="{{ route('payroll.employees.benefits', $employee->id) }}" class="btn btn-success">
                                        <i class="fas fa-plus-circle"></i> {{ _lang('Manage Benefits') }}
                                    </a>
                                    <button type="button" class="btn btn-{{ $employee->is_active ? 'danger' : 'success' }} toggle-status" 
                                            data-id="{{ $employee->id }}" data-status="{{ $employee->is_active }}">
                                        <i class="fas fa-{{ $employee->is_active ? 'ban' : 'check' }}"></i> 
                                        {{ $employee->is_active ? _lang('Deactivate') : _lang('Activate') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Payroll History -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title">{{ _lang('Recent Payroll History') }}</h5>
                            </div>
                            <div class="card-body">
                                @if($payrollHistory->count() > 0)
                                    @foreach($payrollHistory as $item)
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y') }}</small>
                                                <div class="fw-bold">{{ $item->payrollPeriod->name ?? 'N/A' }}</div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold">{{ number_format($item->net_pay, 2) }}</div>
                                                <small class="text-muted">{{ $item->payrollPeriod->status ?? 'N/A' }}</small>
                                            </div>
                                        </div>
                                        <hr>
                                    @endforeach
                                    <a href="{{ route('payroll.employees.payroll-history', $employee->id) }}" class="btn btn-sm btn-outline-primary">
                                        {{ _lang('View All History') }}
                                    </a>
                                @else
                                    <p class="text-muted">{{ _lang('No payroll history found') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Toggle employee status
    $('.toggle-status').click(function() {
        var id = $(this).data('id');
        var status = $(this).data('status');
        var button = $(this);
        
        $.ajax({
            url: "{{ url('payroll/employees') }}/" + id + "/toggle-status",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if(response.result == 'success') {
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('{{ _lang("An error occurred") }}');
            }
        });
    });
});
</script>
@endsection
