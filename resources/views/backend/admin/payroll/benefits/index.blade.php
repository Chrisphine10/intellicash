@extends('layouts.app')

@section('title', _lang('Payroll Benefits'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Payroll Benefits') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.benefits.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Add Benefit') }}
                    </a>
                    <button type="button" class="btn btn-success btn-sm" id="create-defaults">
                        <i class="fas fa-magic"></i> {{ _lang('Create Defaults') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="benefits-table">
                        <thead>
                            <tr>
                                <th>{{ _lang('Code') }}</th>
                                <th>{{ _lang('Name') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Rate/Amount') }}</th>
                                <th>{{ _lang('Employer Paid') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($benefits as $benefit)
                            <tr>
                                <td><span class="badge bg-secondary">{{ $benefit->code }}</span></td>
                                <td>
                                    <div>
                                        <div class="fw-bold">{{ $benefit->name }}</div>
                                        @if($benefit->description)
                                            <small class="text-muted">{{ $benefit->description }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $benefit->type)) }}</span>
                                </td>
                                <td>
                                    @if($benefit->type === 'percentage')
                                        {{ $benefit->rate }}%
                                    @elseif($benefit->type === 'fixed_amount')
                                        {{ number_format($benefit->amount, 2) }}
                                    @else
                                        {{ _lang('Tiered') }}
                                    @endif
                                </td>
                                <td>
                                    @if($benefit->is_employer_paid)
                                        <span class="badge bg-success">{{ _lang('Yes') }}</span>
                                    @else
                                        <span class="badge bg-warning">{{ _lang('No') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($benefit->is_active)
                                        <span class="badge bg-success">{{ _lang('Active') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ _lang('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('payroll.benefits.show', $benefit->id) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('payroll.benefits.edit', $benefit->id) }}" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-{{ $benefit->is_active ? 'danger' : 'success' }} btn-sm toggle-status" 
                                                data-id="{{ $benefit->id }}" data-status="{{ $benefit->is_active }}">
                                            <i class="fas fa-{{ $benefit->is_active ? 'ban' : 'check' }}"></i>
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
                    {{ $benefits->links() }}
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
    $('#benefits-table').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [6] }
        ]
    });

    // Toggle benefit status
    $('.toggle-status').click(function() {
        var id = $(this).data('id');
        var status = $(this).data('status');
        var button = $(this);
        
        $.ajax({
            url: "{{ url('payroll/benefits') }}/" + id + "/toggle-status",
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

    // Create default benefits
    $('#create-defaults').click(function() {
        if(confirm('{{ _lang("This will create default benefits. Continue?") }}')) {
            $.ajax({
                url: "{{ url('payroll/benefits/create-defaults') }}",
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
