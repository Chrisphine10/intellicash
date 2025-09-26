@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <span class="panel-title">{{ _lang('Lease Request Details') }}</span>
                    <a class="btn btn-secondary btn-sm float-right" href="{{ route('lease-requests.index') }}">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            <h5>{{ _lang('Request Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Request Number') }}:</strong></td>
                                    <td>{{ $leaseRequest->request_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        @if($leaseRequest->status == 'pending')
                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                        @elseif($leaseRequest->status == 'approved')
                                            <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                        @elseif($leaseRequest->status == 'rejected')
                                            <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Requested Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->created_at }}</td>
                                </tr>
                                @if($leaseRequest->processed_at)
                                <tr>
                                    <td><strong>{{ _lang('Processed Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->processed_at }}</td>
                                </tr>
                                @endif
                                @if($leaseRequest->processedBy)
                                <tr>
                                    <td><strong>{{ _lang('Processed By') }}:</strong></td>
                                    <td>{{ $leaseRequest->processedBy->name }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5>{{ _lang('Member Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Name') }}:</strong></td>
                                    <td>{{ $leaseRequest->member->first_name }} {{ $leaseRequest->member->last_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Member Number') }}:</strong></td>
                                    <td>{{ $leaseRequest->member->member_no }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Email') }}:</strong></td>
                                    <td>{{ $leaseRequest->member->email ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Mobile') }}:</strong></td>
                                    <td>{{ $leaseRequest->member->mobile ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5>{{ _lang('Asset Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Asset Name') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Category') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->category->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Asset Code') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->asset_code }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Description') }}:</strong></td>
                                    <td>{{ $leaseRequest->asset->description ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Current Status') }}:</strong></td>
                                    <td>
                                        @if($leaseRequest->asset->isAvailableForLease())
                                            <span class="badge badge-success">{{ _lang('Available') }}</span>
                                        @else
                                            <span class="badge badge-warning">{{ _lang('Not Available') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5>{{ _lang('Lease Details') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Start Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->start_date }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('End Date') }}:</strong></td>
                                    <td>{{ $leaseRequest->end_date ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Duration') }}:</strong></td>
                                    <td>{{ $leaseRequest->requested_days }} {{ _lang('days') }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Daily Rate') }}:</strong></td>
                                    <td>{{ formatAmount($leaseRequest->daily_rate) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Amount') }}:</strong></td>
                                    <td>{{ formatAmount($leaseRequest->total_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Deposit Amount') }}:</strong></td>
                                    <td>{{ formatAmount($leaseRequest->deposit_amount) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h5>{{ _lang('Payment Information') }}</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Payment Account') }}:</strong></td>
                                    <td>{{ $leaseRequest->paymentAccount->account_number }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Account Type') }}:</strong></td>
                                    <td>{{ $leaseRequest->paymentAccount->savings_type->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Account Balance') }}:</strong></td>
                                    <td>{{ formatAmount(get_account_balance($leaseRequest->payment_account_id, $leaseRequest->member_id)) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Payment Required') }}:</strong></td>
                                    <td><strong>{{ formatAmount($leaseRequest->total_amount + $leaseRequest->deposit_amount) }}</strong></td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h5>{{ _lang('Actions') }}</h5>
                            @if($leaseRequest->status == 'pending')
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-success" onclick="approveRequest({{ $leaseRequest->id }})">
                                        <i class="fas fa-check"></i> {{ _lang('Approve Request') }}
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="rejectRequest({{ $leaseRequest->id }})">
                                        <i class="fas fa-times"></i> {{ _lang('Reject Request') }}
                                    </button>
                                </div>
                            @elseif($leaseRequest->status == 'approved')
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> {{ _lang('This request has been approved and payment processed.') }}
                                </div>
                            @elseif($leaseRequest->status == 'rejected')
                                <div class="alert alert-danger">
                                    <i class="fas fa-times-circle"></i> {{ _lang('This request has been rejected.') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h5>{{ _lang('Reason for Lease') }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    {{ $leaseRequest->reason }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($leaseRequest->admin_notes)
                    <div class="row">
                        <div class="col-md-12">
                            <h5>{{ _lang('Admin Notes') }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    {{ $leaseRequest->admin_notes }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($leaseRequest->rejection_reason)
                    <div class="row">
                        <div class="col-md-12">
                            <h5>{{ _lang('Rejection Reason') }}</h5>
                            <div class="card">
                                <div class="card-body">
                                    <div class="alert alert-danger">
                                        {{ $leaseRequest->rejection_reason }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Approve Lease Request') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        {{ _lang('Approving this request will process payment from the member\'s account and create an active lease.') }}
                    </div>
                    <div class="form-group">
                        <label>{{ _lang('Admin Notes') }}</label>
                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="{{ _lang('Optional notes for the member') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                    <button type="submit" class="btn btn-success">{{ _lang('Approve Request') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Reject Lease Request') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ _lang('Rejection Reason') }} <span class="required">*</span></label>
                        <textarea class="form-control" name="rejection_reason" rows="3" required placeholder="{{ _lang('Please provide a reason for rejection') }}"></textarea>
                    </div>
                    <div class="form-group">
                        <label>{{ _lang('Admin Notes') }}</label>
                        <textarea class="form-control" name="admin_notes" rows="3" placeholder="{{ _lang('Optional notes for the member') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ _lang('Reject Request') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveRequest(requestId) {
    document.getElementById('approveForm').action = '{{ route("lease-requests.approve", ":id") }}'.replace(':id', requestId);
    $('#approveModal').modal('show');
}

function rejectRequest(requestId) {
    document.getElementById('rejectForm').action = '{{ route("lease-requests.reject", ":id") }}'.replace(':id', requestId);
    $('#rejectModal').modal('show');
}
</script>
@endsection
