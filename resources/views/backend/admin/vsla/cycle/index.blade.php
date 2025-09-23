@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ _lang('VSLA Cycles') }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title">{{ _lang('VSLA Cycle Management') }}</h4>
                    <div>
                        <a href="{{ route('vsla.cycles.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> {{ _lang('Create New Cycle') }}
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="cycles_table" class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ _lang('Cycle Name') }}</th>
                                <th>{{ _lang('Start Date') }}</th>
                                <th>{{ _lang('End Date') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Phase') }}</th>
                                <th>{{ _lang('Members') }}</th>
                                <th>{{ _lang('Total Fund') }}</th>
                                <th>{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    "use strict";
    
    $('#cycles_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('vsla.cycles.get_table_data') }}",
            type: "GET"
        },
        columns: [
            { data: 'cycle_name', name: 'cycle_name' },
            { data: 'start_date', name: 'start_date' },
            { data: 'end_date', name: 'end_date', orderable: false },
            { data: 'status', name: 'status' },
            { data: 'phase', name: 'phase', orderable: false },
            { data: 'participating_members', name: 'participating_members', orderable: false },
            { data: 'total_available_for_shareout', name: 'total_available_for_shareout' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        responsive: true,
        language: {
            processing: "{{ _lang('Processing') }}...",
            search: "{{ _lang('Search') }}:",
            lengthMenu: "{{ _lang('Show') }} _MENU_ {{ _lang('entries') }}",
            info: "{{ _lang('Showing') }} _START_ {{ _lang('to') }} _END_ {{ _lang('of') }} _TOTAL_ {{ _lang('entries') }}",
            infoEmpty: "{{ _lang('Showing') }} 0 {{ _lang('to') }} 0 {{ _lang('of') }} 0 {{ _lang('entries') }}",
            infoFiltered: "({{ _lang('filtered from') }} _MAX_ {{ _lang('total entries') }})",
            paginate: {
                first: "{{ _lang('First') }}",
                last: "{{ _lang('Last') }}",
                next: "{{ _lang('Next') }}",
                previous: "{{ _lang('Previous') }}"
            },
            emptyTable: "{{ _lang('No cycles found') }}"
        },
        drawCallback: function() {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
});
</script>
@endsection
