@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <span class="panel-title">{{ _lang('Lease Requests Management') }}</span>
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

                    <!-- Filter Form -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <select name="status" class="form-control">
                                    <option value="">{{ _lang('All Status') }}</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ _lang('Pending') }}</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ _lang('Approved') }}</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ _lang('Rejected') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="member_id" class="form-control">
                                    <option value="">{{ _lang('All Members') }}</option>
                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}" {{ request('member_id') == $member->id ? 'selected' : '' }}>
                                            {{ $member->first_name }} {{ $member->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="asset_id" class="form-control">
                                    <option value="">{{ _lang('All Assets') }}</option>
                                    @foreach($assets as $asset)
                                        <option value="{{ $asset->id }}" {{ request('asset_id') == $asset->id ? 'selected' : '' }}>
                                            {{ $asset->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">{{ _lang('Filter') }}</button>
                                <a href="{{ route('lease-requests.index') }}" class="btn btn-secondary">{{ _lang('Clear') }}</a>
                            </div>
                        </div>
                    </form>

                    @if($requests->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Request #') }}</th>
                                        <th>{{ _lang('Member') }}</th>
                                        <th>{{ _lang('Asset') }}</th>
                                        <th>{{ _lang('Start Date') }}</th>
                                        <th>{{ _lang('Duration') }}</th>
                                        <th>{{ _lang('Total Amount') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Requested Date') }}</th>
                                        <th>{{ _lang('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $request)
                                        <tr>
                                            <td>{{ $request->request_number }}</td>
                                            <td>
                                                <strong>{{ $request->member->first_name }} {{ $request->member->last_name }}</strong><br>
                                                <small class="text-muted">{{ $request->member->member_no }}</small>
                                            </td>
                                            <td>
                                                <strong>{{ $request->asset->name }}</strong><br>
                                                <small class="text-muted">{{ $request->asset->category->name ?? 'N/A' }}</small>
                                            </td>
                                            <td>{{ $request->start_date }}</td>
                                            <td>{{ $request->requested_days }} {{ _lang('days') }}</td>
                                            <td>{{ formatAmount($request->total_amount) }}</td>
                                            <td>
                                                @if($request->status == 'pending')
                                                    <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                                @elseif($request->status == 'approved')
                                                    <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                                @elseif($request->status == 'rejected')
                                                    <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $request->created_at }}</td>
                                            <td>
                                                <a href="{{ route('lease-requests.show', $request) }}" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> {{ _lang('View') }}
                                                </a>
                                                @if($request->status == 'pending')
                                                    <button type="button" class="btn btn-success btn-sm" onclick="approveRequest({{ $request->id }})">
                                                        <i class="fas fa-check"></i> {{ _lang('Approve') }}
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="rejectRequest({{ $request->id }})">
                                                        <i class="fas fa-times"></i> {{ _lang('Reject') }}
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{ $requests->links() }}
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> {{ _lang('No lease requests found') }}
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
