@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <span class="panel-title">{{ _lang('Transfer History') }}</span>
                <div>
                    <a class="btn btn-primary btn-xs" href="{{ route('transfer.own_account_transfer') }}">
                        <i class="fas fa-plus mr-1"></i>{{ _lang('Own Account Transfer') }}
                    </a>
                    <a class="btn btn-success btn-xs" href="{{ route('transfer.other_account_transfer') }}">
                        <i class="fas fa-plus mr-1"></i>{{ _lang('Other Account Transfer') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if($transfers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Date') }}</th>
                                    <th>{{ _lang('Transfer Type') }}</th>
                                    <th>{{ _lang('Beneficiary') }}</th>
                                    <th>{{ _lang('Amount') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Description') }}</th>
                                    <th>{{ _lang('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->created_at->format('M d, Y h:i A') }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $transfer->transfer_type_text }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $transfer->beneficiary_name }}</strong><br>
                                        <small class="text-muted">Account: {{ $transfer->beneficiary_account }}</small>
                                    </td>
                                    <td class="text-right">
                                        {{ decimalPlace($transfer->amount, currency('KES')) }}
                                    </td>
                                    <td>
                                        @if($transfer->status == 0)
                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                        @elseif($transfer->status == 1)
                                            <span class="badge badge-info">{{ _lang('Processing') }}</span>
                                        @elseif($transfer->status == 2)
                                            <span class="badge badge-success">{{ _lang('Completed') }}</span>
                                        @else
                                            <span class="badge badge-danger">{{ _lang('Failed') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $transfer->description ?: '-' }}</td>
                                    <td>
                                        <button class="btn btn-info btn-xs" onclick="viewTransferDetails({{ $transfer->id }})">
                                            <i class="fas fa-eye"></i> {{ _lang('View') }}
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        {{ $transfers->links() }}
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ _lang('No transfers found') }}</h5>
                        <p class="text-muted">{{ _lang('You haven\'t made any transfers yet') }}</p>
                        <div>
                            <a href="{{ route('transfer.own_account_transfer') }}" class="btn btn-primary mr-2">
                                <i class="fas fa-plus mr-1"></i>{{ _lang('Own Account Transfer') }}
                            </a>
                            <a href="{{ route('transfer.other_account_transfer') }}" class="btn btn-success">
                                <i class="fas fa-plus mr-1"></i>{{ _lang('Other Account Transfer') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Transfer Details Modal -->
<div class="modal fade" id="transferDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Transfer Details') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="transferDetailsContent">
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
function viewTransferDetails(transferId) {
    // Show loading
    $('#transferDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> {{ _lang("Loading...") }}</div>');
    $('#transferDetailsModal').modal('show');
    
    // Load transfer details via AJAX
    $.ajax({
        url: '{{ route("transfer.details", ":id") }}'.replace(':id', transferId),
        type: 'GET',
        success: function(data) {
            $('#transferDetailsContent').html(data);
        },
        error: function() {
            $('#transferDetailsContent').html('<div class="alert alert-danger">{{ _lang("Error loading transfer details") }}</div>');
        }
    });
}
</script>
@endsection
