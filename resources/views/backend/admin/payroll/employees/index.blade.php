@extends('layouts.app')

@section('title', _lang('Employee Management'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Employee Management') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.employees.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Add Employee') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ _lang('Total Employees') }}</span>
                                <span class="info-box-number">{{ $stats['total_employees'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ _lang('Active Employees') }}</span>
                                <span class="info-box-number">{{ $stats['active_employees'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-building"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ _lang('Departments') }}</span>
                                <span class="info-box-number">{{ $stats['departments'] }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-calendar"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ _lang('This Month') }}</span>
                                <span class="info-box-number">{{ $stats['total_employees'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="employees-table">
                        <thead>
                            <tr>
                                <th>{{ _lang('Employee ID') }}</th>
                                <th>{{ _lang('Name') }}</th>
                                <th>{{ _lang('Email') }}</th>
                                <th>{{ _lang('Phone') }}</th>
                                <th>{{ _lang('Department') }}</th>
                                <th>{{ _lang('Job Title') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $employee)
                            <tr>
                                <td>{{ $employee->employee_id }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                            <span class="text-white fw-bold">{{ substr($employee->first_name, 0, 1) }}</span>
                                        </div>
                                        <div>
                                            <div class="fw-bold">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                                            <small class="text-muted">{{ $employee->employment_type }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $employee->email ?? '-' }}</td>
                                <td>{{ $employee->phone ?? '-' }}</td>
                                <td>{{ $employee->department ?? '-' }}</td>
                                <td>{{ $employee->job_title }}</td>
                                <td>
                                    @if($employee->is_active)
                                        <span class="badge bg-success">{{ _lang('Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ _lang('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('payroll.employees.edit', $employee->id) }}" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-{{ $employee->is_active ? 'danger' : 'success' }} btn-sm toggle-status" 
                                                data-id="{{ $employee->id }}" data-status="{{ $employee->is_active }}">
                                            <i class="fas fa-{{ $employee->is_active ? 'ban' : 'check' }}"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $employees->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#employees-table').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'desc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });

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
