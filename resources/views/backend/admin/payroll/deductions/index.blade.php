@extends('layouts.app')

@section('title', _lang('Payroll Deductions'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Payroll Deductions') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.deductions.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Add Deduction') }}
                    </a>
                    <button type="button" class="btn btn-success btn-sm" id="create-defaults">
                        <i class="fas fa-magic"></i> {{ _lang('Create Defaults') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="deductions-table">
                        <thead>
                            <tr>
                                <th>{{ _lang('Code') }}</th>
                                <th>{{ _lang('Name') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Rate/Amount') }}</th>
                                <th>{{ _lang('Mandatory') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deductions as $deduction)
                            <tr>
                                <td><span class="badge bg-secondary">{{ $deduction->code }}</span></td>
                                <td>
                                    <div>
                                        <div class="fw-bold">{{ $deduction->name }}</div>
                                        @if($deduction->description)
                                            <small class="text-muted">{{ $deduction->description }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $deduction->type)) }}</span>
                                </td>
                                <td>
                                    @if($deduction->type === 'percentage')
                                        {{ $deduction->rate }}%
                                    @elseif($deduction->type === 'fixed_amount')
                                        {{ number_format($deduction->amount, 2) }}
                                    @else
                                        {{ _lang('Tiered') }}
                                    @endif
                                </td>
                                <td>
                                    @if($deduction->is_mandatory)
                                        <span class="badge bg-danger">{{ _lang('Yes') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ _lang('No') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($deduction->is_active)
                                        <span class="badge bg-success">{{ _lang('Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ _lang('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('payroll.deductions.show', $deduction->id) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('payroll.deductions.edit', $deduction->id) }}" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-{{ $deduction->is_active ? 'danger' : 'success' }} btn-sm toggle-status" 
                                                data-id="{{ $deduction->id }}" data-status="{{ $deduction->is_active }}">
                                            <i class="fas fa-{{ $deduction->is_active ? 'ban' : 'check' }}"></i>
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
                    {{ $deductions->links() }}
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
    $('#deductions-table').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [6] }
        ]
    });

    // Toggle deduction status
    $('.toggle-status').click(function() {
        var id = $(this).data('id');
        var status = $(this).data('status');
        var button = $(this);
        
        $.ajax({
            url: "{{ url('payroll/deductions') }}/" + id + "/toggle-status",
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

    // Create default deductions
    $('#create-defaults').click(function() {
        if(confirm('{{ _lang("This will create default deductions. Continue?") }}')) {
            $.ajax({
                url: "{{ url('payroll/deductions/create-defaults') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if(response.result == 'success') {
                        toastr.success(response.message);
                        location.reload();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('{{ _lang("An error occurred") }}');
                }
            });
        }
    });
});
</script>
@endsection
