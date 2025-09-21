@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <span class="panel-title">{{ _lang('Withdrawal Requests') }}</span>
                <div>
                    <a class="btn btn-primary btn-xs" href="{{ route('withdraw.manual_methods') }}">
                        <i class="fas fa-plus mr-1"></i>{{ _lang('New Withdrawal Request') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($withdrawRequests->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Request ID') }}</th>
                                    <th>{{ _lang('Date') }}</th>
                                    <th>{{ _lang('Method') }}</th>
                                    <th>{{ _lang('Account') }}</th>
                                    <th>{{ _lang('Amount') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Description') }}</th>
                                    <th>{{ _lang('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($withdrawRequests as $request)
                                <tr>
                                    <td>#{{ $request->id }}</td>
                                    <td>{{ $request->created_at }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $request->method->name ?? 'N/A' }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $request->account->account_number ?? 'N/A' }}</strong><br>
                                        <small class="text-muted">{{ $request->account->savings_type->name ?? 'N/A' }}</small>
                                    </td>
                                    <td class="text-right">
                                        {{ decimalPlace($request->amount, currency($request->account->savings_type->currency->name ?? 'KES')) }}
                                    </td>
                                    <td>
                                        @if($request->status == 0)
                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                        @elseif($request->status == 1)
                                            <span class="badge badge-info">{{ _lang('Processing') }}</span>
                                        @elseif($request->status == 2)
                                            <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                        @else
                                            <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $request->description ?: '-' }}</td>
                                    <td>
                                        <button class="btn btn-info btn-xs" onclick="viewRequestDetails({{ $request->id }})">
                                            <i class="fas fa-eye"></i> {{ _lang('View') }}
                                        </button>
                                        @if($request->transaction_id)
                                            <a href="{{ route('trasnactions.details', $request->transaction_id) }}" class="btn btn-secondary btn-xs" target="_blank">
                                                <i class="fas fa-receipt"></i> {{ _lang('Transaction') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        {{ $withdrawRequests->links() }}
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ _lang('No withdrawal requests found') }}</h5>
                        <p class="text-muted">{{ _lang('You haven\'t submitted any withdrawal requests yet') }}</p>
                        <a href="{{ route('withdraw.manual_methods') }}" class="btn btn-primary">
                            <i class="fas fa-plus mr-1"></i>{{ _lang('Submit Your First Request') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade" id="requestDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Withdrawal Request Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="requestDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Close') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
function viewRequestDetails(requestId) {
    // Show loading
    $('#requestDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> {{ _lang("Loading...") }}</div>');
    $('#requestDetailsModal').modal('show');
    
    // Load request details via AJAX
    $.ajax({
        url: '{{ route("withdraw.request_details", ":id") }}'.replace(':id', requestId),
        type: 'GET',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(data) {
            $('#requestDetailsContent').html(data);
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Response:', xhr.responseText);
            $('#requestDetailsContent').html('<div class="alert alert-danger">{{ _lang("Error loading request details") }}: ' + error + '</div>');
        }
    });
}
</script>
@endsection
