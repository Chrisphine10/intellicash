@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('Withdrawal Requests') }}</span>
                <div class="ml-auto">
                    <span class="badge badge-warning">{{ $withdrawRequests->where('status', 0)->count() }} {{ _lang('Pending') }}</span>
                </div>
            </div>
            <div class="card-body">
                <table id="withdrawal_requests_table" class="table table-bordered data-table">
                    <thead>
                        <tr>
                            <th>{{ _lang('Date') }}</th>
                            <th>{{ _lang('Member') }}</th>
                            <th>{{ _lang('Amount') }}</th>
                            <th>{{ _lang('Payment Method') }}</th>
                            <th>{{ _lang('Recipient') }}</th>
                            <th>{{ _lang('Status') }}</th>
                            <th class="text-center">{{ _lang('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdrawRequests as $request)
                        <tr data-id="row_{{ $request->id }}">
                            <td>{{ $request->created_at->format('M d, Y H:i') }}</td>
                            <td>
                                {{ $request->member->first_name }} {{ $request->member->last_name }}<br>
                                <small class="text-muted">{{ $request->member->member_no }}</small>
                            </td>
                            <td>{{ decimalPlace($request->amount, currency($request->account->savings_type->currency->name)) }}</td>
                            <td>
                                @php
                                    $requirements = json_decode($request->requirements, true);
                                    $paymentMethodType = $requirements['payment_method_type'] ?? 'Unknown';
                                @endphp
                                <span class="badge badge-info">{{ ucfirst($paymentMethodType) }}</span>
                            </td>
                            <td>
                                @php
                                    $recipientDetails = $requirements['recipient_details'] ?? [];
                                @endphp
                                {{ $recipientDetails['name'] ?? 'N/A' }}<br>
                                <small class="text-muted">{{ $recipientDetails['mobile'] ?? '' }}</small>
                            </td>
                            <td>
                                @if($request->status == 0)
                                    <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                @elseif($request->status == 2)
                                    <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                @elseif($request->status == 3)
                                    <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.withdrawal_requests.show', $request->id) }}" 
                                   class="btn btn-primary btn-xs" title="{{ _lang('View Details') }}">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($request->status == 0)
                                    <button class="btn btn-success btn-xs approve-btn" 
                                            data-id="{{ $request->id }}" 
                                            data-member="{{ $request->member->first_name }} {{ $request->member->last_name }}"
                                            data-amount="{{ decimalPlace($request->amount, currency($request->account->savings_type->currency->name)) }}"
                                            title="{{ _lang('Approve') }}">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-danger btn-xs reject-btn" 
                                            data-id="{{ $request->id }}"
                                            data-member="{{ $request->member->first_name }} {{ $request->member->last_name }}"
                                            data-amount="{{ decimalPlace($request->amount, currency($request->account->savings_type->currency->name)) }}"
                                            title="{{ _lang('Reject') }}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Approve Withdrawal Request') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>{{ _lang('Are you sure you want to approve this withdrawal request?') }}</p>
                <div class="alert alert-info">
                    <strong>{{ _lang('Member') }}:</strong> <span id="approve-member-name"></span><br>
                    <strong>{{ _lang('Amount') }}:</strong> <span id="approve-amount"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                <form id="approve-form" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-success">{{ _lang('Approve') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Reject Withdrawal Request') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="reject-form" method="POST">
                @csrf
                <div class="modal-body">
                    <p>{{ _lang('Please provide a reason for rejecting this withdrawal request:') }}</p>
                    <div class="alert alert-warning">
                        <strong>{{ _lang('Member') }}:</strong> <span id="reject-member-name"></span><br>
                        <strong>{{ _lang('Amount') }}:</strong> <span id="reject-amount"></span>
                    </div>
                    <div class="form-group">
                        <label for="rejection_reason">{{ _lang('Rejection Reason') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ _lang('Reject') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Approve button click
    $('.approve-btn').on('click', function() {
        var id = $(this).data('id');
        var member = $(this).data('member');
        var amount = $(this).data('amount');
        
        $('#approve-member-name').text(member);
        $('#approve-amount').text(amount);
        $('#approve-form').attr('action', '{{ route("admin.withdrawal_requests.approve", ":id") }}'.replace(':id', id));
        $('#approveModal').modal('show');
    });

    // Reject button click
    $('.reject-btn').on('click', function() {
        var id = $(this).data('id');
        var member = $(this).data('member');
        var amount = $(this).data('amount');
        
        $('#reject-member-name').text(member);
        $('#reject-amount').text(amount);
        $('#reject-form').attr('action', '{{ route("admin.withdrawal_requests.reject", ":id") }}'.replace(':id', id));
        $('#rejectModal').modal('show');
    });
});
</script>
@endsection
