@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('My Activity Log') }}</span>
            </div>

            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control" id="event_type_filter">
                            <option value="">{{ _lang('All Activities') }}</option>
                            @foreach($eventTypes as $eventType)
                                <option value="{{ $eventType }}">{{ ucfirst(str_replace('_', ' ', $eventType)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="auditable_type_filter">
                            <option value="">{{ _lang('All Models') }}</option>
                            @foreach($auditableTypes as $auditableType)
                                <option value="{{ $auditableType }}">{{ class_basename($auditableType) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" id="date_from_filter" placeholder="{{ _lang('From Date') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" id="date_to_filter" placeholder="{{ _lang('To Date') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-filter"></i> {{ _lang('Filter') }}
                        </button>
                    </div>
                </div>

                <!-- Activity Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('Total Activities') }}</h5>
                                <h3 id="total_activities">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('Today') }}</h5>
                                <h3 id="today_activities">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('This Week') }}</h5>
                                <h3 id="week_activities">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('This Month') }}</h5>
                                <h3 id="month_activities">-</h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DataTable -->
                <div class="table-responsive">
                    <table class="table table-bordered" id="audit_table">
                        <thead>
                            <tr>
                                <th>{{ _lang('Date/Time') }}</th>
                                <th>{{ _lang('Activity') }}</th>
                                <th>{{ _lang('Model') }}</th>
                                <th>{{ _lang('Changes') }}</th>
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
    // Initialize DataTable
    var table = $('#audit_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('member.audit.get_table_data') }}",
            data: function(d) {
                d.event_type = $('#event_type_filter').val();
                d.auditable_type = $('#auditable_type_filter').val();
                d.date_from = $('#date_from_filter').val();
                d.date_to = $('#date_to_filter').val();
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'event_type', name: 'event_type' },
            { data: 'auditable_name', name: 'auditable_name' },
            { data: 'changes', name: 'changes', orderable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true
    });

    // Load statistics
    loadStatistics();
});

function applyFilters() {
    $('#audit_table').DataTable().ajax.reload();
    loadStatistics();
}

function loadStatistics() {
    $.ajax({
        url: "{{ route('member.audit.summary') }}",
        data: {
            date_from: $('#date_from_filter').val(),
            date_to: $('#date_to_filter').val()
        },
        success: function(data) {
            $('#total_activities').text(data.total_activities);
            
            // Calculate today's activities
            var today = new Date().toISOString().split('T')[0];
            var todayActivities = 0;
            for (var type in data.activities_by_type) {
                todayActivities += data.activities_by_type[type];
            }
            $('#today_activities').text(todayActivities);
            
            // You can add more specific calculations here
            $('#week_activities').text('-');
            $('#month_activities').text('-');
        }
    });
}
</script>
@endsection
