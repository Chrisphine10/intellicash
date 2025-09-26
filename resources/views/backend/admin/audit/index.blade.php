@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Audit Trail') }}</span>
                <div class="float-right">
                    <button class="btn btn-primary btn-sm" onclick="exportAudit()">
                        <i class="fas fa-download"></i> {{ _lang('Export') }}
                    </button>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <select class="form-control" id="event_type_filter">
                            <option value="">{{ _lang('All Event Types') }}</option>
                            @foreach($eventTypes as $eventType)
                                <option value="{{ $eventType }}">{{ ucfirst(str_replace('_', ' ', $eventType)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="auditable_type_filter">
                            <option value="">{{ _lang('All Models') }}</option>
                            @foreach($auditableTypes as $auditableType)
                                <option value="{{ $auditableType }}">{{ class_basename($auditableType) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-control" id="user_type_filter">
                            <option value="">{{ _lang('All User Types') }}</option>
                            @foreach($userTypes as $userType)
                                <option value="{{ $userType }}">{{ ucfirst($userType) }}</option>
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
                        <button class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> {{ _lang('Clear') }}
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('Total Events') }}</h5>
                                <h3 id="total_events">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('Today') }}</h5>
                                <h3 id="today_events">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('This Week') }}</h5>
                                <h3 id="week_events">-</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">{{ _lang('This Month') }}</h5>
                                <h3 id="month_events">-</h3>
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
                                <th>{{ _lang('Event Type') }}</th>
                                <th>{{ _lang('User') }}</th>
                                <th>{{ _lang('Model') }}</th>
                                <th>{{ _lang('Changes') }}</th>
                                <th>{{ _lang('IP Address') }}</th>
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

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Export Audit Trails') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="exportForm">
                    <div class="form-group">
                        <label>{{ _lang('Event Type') }}</label>
                        <select class="form-control" name="event_type">
                            <option value="">{{ _lang('All Event Types') }}</option>
                            @foreach($eventTypes as $eventType)
                                <option value="{{ $eventType }}">{{ ucfirst(str_replace('_', ' ', $eventType)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ _lang('Date From') }}</label>
                        <input type="date" class="form-control" name="date_from">
                    </div>
                    <div class="form-group">
                        <label>{{ _lang('Date To') }}</label>
                        <input type="date" class="form-control" name="date_to">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="downloadExport()">{{ _lang('Export') }}</button>
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
            url: "{{ route('audit.get_table_data') }}",
            data: function(d) {
                d.event_type = $('#event_type_filter').val();
                d.auditable_type = $('#auditable_type_filter').val();
                d.user_type = $('#user_type_filter').val();
                d.date_from = $('#date_from_filter').val();
                d.date_to = $('#date_to_filter').val();
            }
        },
        columns: [
            { data: 'created_at', name: 'created_at' },
            { data: 'event_type', name: 'event_type' },
            { data: 'user_name', name: 'user_name' },
            { data: 'auditable_name', name: 'auditable_name' },
            { data: 'changes', name: 'changes', orderable: false },
            { data: 'ip_address', name: 'ip_address' },
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

function clearFilters() {
    $('#event_type_filter').val('');
    $('#auditable_type_filter').val('');
    $('#user_type_filter').val('');
    $('#date_from_filter').val('');
    $('#date_to_filter').val('');
    applyFilters();
}

function loadStatistics() {
    $.ajax({
        url: "{{ route('audit.statistics') }}",
        data: {
            date_from: $('#date_from_filter').val(),
            date_to: $('#date_to_filter').val(),
            event_type: $('#event_type_filter').val(),
            auditable_type: $('#auditable_type_filter').val(),
            user_type: $('#user_type_filter').val()
        },
        success: function(data) {
            // Total events
            $('#total_events').text(data.total_events || 0);
            
            // Today's events
            $('#today_events').text(data.today_events || 0);
            
            // This week's events
            $('#week_events').text(data.week_events || 0);
            
            // This month's events
            $('#month_events').text(data.month_events || 0);
        },
        error: function(xhr, status, error) {
            console.error('Error loading statistics:', error);
            $('#total_events').text('0');
            $('#today_events').text('0');
            $('#week_events').text('0');
            $('#month_events').text('0');
        }
    });
}

function exportAudit() {
    $('#exportModal').modal('show');
}

function downloadExport() {
    var formData = $('#exportForm').serialize();
    window.location.href = "{{ route('audit.export') }}?" + formData;
    $('#exportModal').modal('hide');
}
</script>
@endsection
