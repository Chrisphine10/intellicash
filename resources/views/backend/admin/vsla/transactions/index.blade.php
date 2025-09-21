@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('VSLA Transactions') }}</h4>
                <div class="float-right">
                    <a href="{{ route('vsla.transactions.bulk_create') }}" class="btn btn-success btn-sm mr-2">
                        <i class="fas fa-list"></i> {{ _lang('Bulk Transactions') }}
                    </a>
                    <a href="{{ route('vsla.transactions.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('New Transaction') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control" id="meeting_filter">
                            <option value="">{{ _lang('All Meetings') }}</option>
                            @foreach($meetings as $meeting)
                            <option value="{{ $meeting->id }}" {{ request('meeting_id') == $meeting->id ? 'selected' : '' }}>
                                {{ $meeting->meeting_number }} - {{ $meeting->meeting_date }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="type_filter">
                            <option value="">{{ _lang('All Types') }}</option>
                            <option value="share_purchase" {{ request('transaction_type') == 'share_purchase' ? 'selected' : '' }}>{{ _lang('Share Purchase') }}</option>
                            <option value="loan_issuance" {{ request('transaction_type') == 'loan_issuance' ? 'selected' : '' }}>{{ _lang('Loan Issuance') }}</option>
                            <option value="loan_repayment" {{ request('transaction_type') == 'loan_repayment' ? 'selected' : '' }}>{{ _lang('Loan Repayment') }}</option>
                            <option value="penalty_fine" {{ request('transaction_type') == 'penalty_fine' ? 'selected' : '' }}>{{ _lang('Penalty Fine') }}</option>
                            <option value="welfare_contribution" {{ request('transaction_type') == 'welfare_contribution' ? 'selected' : '' }}>{{ _lang('Welfare Contribution') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="status_filter">
                            <option value="">{{ _lang('All Status') }}</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ _lang('Pending') }}</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ _lang('Approved') }}</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ _lang('Rejected') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-primary" onclick="applyFilters()">{{ _lang('Apply Filters') }}</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Meeting') }}</th>
                                <th>{{ _lang('Member') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Amount') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Date') }}</th>
                                <th>{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->meeting->meeting_number }}</td>
                                <td>{{ $transaction->member->first_name }} {{ $transaction->member->last_name }}</td>
                                <td>
                                    <span class="badge badge-info">
                                        {{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}
                                    </span>
                                </td>
                                <td>{{ number_format($transaction->amount, 2) }}</td>
                                <td>
                                    <span class="badge badge-{{ $transaction->status == 'approved' ? 'success' : ($transaction->status == 'rejected' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($transaction->status) }}
                                    </span>
                                </td>
                                <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($transaction->status == 'pending')
                                    <button type="button" class="btn btn-success btn-sm" onclick="approveTransaction({{ $transaction->id }})">
                                        <i class="fas fa-check"></i> {{ _lang('Approve') }}
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="rejectTransaction({{ $transaction->id }})">
                                        <i class="fas fa-times"></i> {{ _lang('Reject') }}
                                    </button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-center">
                    {{ $transactions->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
function applyFilters() {
    var meetingId = $('#meeting_filter').val();
    var type = $('#type_filter').val();
    var status = $('#status_filter').val();
    
    var url = new URL(window.location);
    if (meetingId) url.searchParams.set('meeting_id', meetingId);
    else url.searchParams.delete('meeting_id');
    
    if (type) url.searchParams.set('transaction_type', type);
    else url.searchParams.delete('transaction_type');
    
    if (status) url.searchParams.set('status', status);
    else url.searchParams.delete('status');
    
    window.location.href = url.toString();
}

function approveTransaction(id) {
    if (confirm('{{ _lang("Are you sure you want to approve this transaction?") }}')) {
        $.ajax({
            url: '{{ url("vsla/transactions") }}/' + id + '/approve',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.result === 'success') {
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('{{ _lang("An error occurred while approving the transaction") }}');
            }
        });
    }
}

function rejectTransaction(id) {
    if (confirm('{{ _lang("Are you sure you want to reject this transaction?") }}')) {
        $.ajax({
            url: '{{ url("vsla/transactions") }}/' + id + '/reject',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.result === 'success') {
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('{{ _lang("An error occurred while rejecting the transaction") }}');
            }
        });
    }
}
</script>
@endsection
