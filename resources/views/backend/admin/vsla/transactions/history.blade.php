@extends('layouts.app')

@section('title', _lang('VSLA Transaction History'))

@section('breadcrumb')
<div class="col-lg-6 col-7">
    <h6 class="h2 text-white d-inline-block mb-0">{{ _lang('VSLA Transaction History') }}</h6>
    <nav aria-label="breadcrumb" class="d-none d-md-inline-block ml-md-4">
        <ol class="breadcrumb breadcrumb-links breadcrumb-dark">
            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}"><i class="fas fa-home"></i></a></li>
            <li class="breadcrumb-item"><a href="{{ route('vsla.meetings.index') }}">{{ _lang('VSLA Meetings') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ _lang('Transaction History') }}</li>
        </ol>
    </nav>
</div>
<div class="col-lg-6 col-5 text-right">
    <a href="{{ route('vsla.meetings.index') }}" class="btn btn-sm btn-neutral">
        <i class="fa fa-arrow-left"></i> {{ _lang('Back to Meetings') }}
    </a>
    @if(auth()->user()->user_type === 'admin' || auth()->user()->user_type === 'superadmin')
    <a href="{{ route('vsla.transactions.bulk_create', ['meeting_id' => $meeting->id]) }}" class="btn btn-sm btn-primary">
        <i class="fa fa-plus"></i> {{ _lang('Add Transactions') }}
    </a>
    @endif
</div>
@endsection

@section('content')
<div class="container-fluid mt--6">
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header border-0">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="d-flex align-items-center">
                                <a href="{{ route('vsla.meetings.index') }}" class="btn btn-sm btn-outline-secondary mr-3">
                                    <i class="fa fa-arrow-left"></i> {{ _lang('Back to Meetings') }}
                                </a>
                                <div>
                                    <h3 class="mb-0">{{ _lang('Transaction History') }}</h3>
                                    <p class="text-sm mb-0">{{ _lang('Meeting') }}: {{ $meeting->title }} - {{ date('M d, Y', strtotime($meeting->meeting_date)) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($transactions->count() > 0)
                        <div class="table-responsive">
                            <table class="table align-items-center table-flush">
                                <thead class="thead-light">
                                    <tr>
                                        <th scope="col">{{ _lang('Member') }}</th>
                                        <th scope="col">{{ _lang('Transaction Type') }}</th>
                                        <th scope="col">{{ _lang('Amount') }}</th>
                                        <th scope="col">{{ _lang('Status') }}</th>
                                        <th scope="col">{{ _lang('Created By') }}</th>
                                        <th scope="col">{{ _lang('Created At') }}</th>
                                        <th scope="col" class="text-right">{{ _lang('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transactions as $transaction)
                                    <tr>
                                        <td>
                                            <div class="media align-items-center">
                                                <div class="media-body">
                                                    <span class="name mb-0 text-sm">{{ $transaction->member->first_name }} {{ $transaction->member->last_name }}</span>
                                                    <div class="text-muted">{{ $transaction->member->member_no }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                {{ ucwords(str_replace('_', ' ', $transaction->transaction_type)) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-weight-bold">{{ number_format($transaction->amount, 2) }} {{ currency_symbol() }}</span>
                                        </td>
                                        <td>
                                            @if($transaction->status === 'approved')
                                                <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                            @elseif($transaction->status === 'pending')
                                                <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                            @elseif($transaction->status === 'rejected')
                                                <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $transaction->createdUser->name ?? _lang('System') }}
                                        </td>
                                        <td>
                                            {{ date('M d, Y H:i', strtotime($transaction->created_at)) }}
                                        </td>
                                        <td class="text-right">
                                            <div class="dropdown">
                                                <a class="btn btn-sm btn-icon-only text-light" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-right dropdown-menu-arrow">
                                                    @if($transaction->description)
                                                    <a class="dropdown-item" href="#" data-toggle="modal" data-target="#descriptionModal{{ $transaction->id }}">
                                                        <i class="fas fa-info-circle text-info"></i> {{ _lang('View Description') }}
                                                    </a>
                                                    @endif
                                                    
                                                    @if($transaction->status === 'pending' && (auth()->user()->user_type === 'admin' || auth()->user()->user_type === 'superadmin'))
                                                    <form action="{{ route('vsla.transactions.approve', $transaction->id) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item text-success" onclick="return confirm('{{ _lang('Are you sure you want to approve this transaction?') }}')">
                                                            <i class="fas fa-check"></i> {{ _lang('Approve') }}
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('vsla.transactions.reject', $transaction->id) }}" method="POST" style="display: inline;">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item text-danger" onclick="return confirm('{{ _lang('Are you sure you want to reject this transaction?') }}')">
                                                            <i class="fas fa-times"></i> {{ _lang('Reject') }}
                                                        </button>
                                                    </form>
                                                    @endif
                                                    
                                                    @if(($transaction->status === 'pending' || $transaction->status === 'approved') && (auth()->user()->user_type === 'admin' || auth()->user()->user_type === 'superadmin' || auth()->user()->role->name === 'VSLA User' || (auth()->user()->member && auth()->user()->member->vslaRoleAssignments()->where('role', 'treasurer')->where('is_active', true)->exists())))
                                                    <a class="dropdown-item" href="{{ route('vsla.transactions.edit', $transaction->id) }}">
                                                        <i class="fas fa-edit text-primary"></i> {{ _lang('Edit') }}
                                                    </a>
                                                    @endif
                                                    
                                                    @if($transaction->status === 'pending' && (auth()->user()->user_type === 'admin' || auth()->user()->user_type === 'superadmin'))
                                                    <form action="{{ route('vsla.transactions.destroy', $transaction->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ _lang('Are you sure you want to delete this transaction?') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash"></i> {{ _lang('Delete') }}
                                                        </button>
                                                    </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    @if($transaction->description)
                                    <!-- Description Modal -->
                                    <div class="modal fade" id="descriptionModal{{ $transaction->id }}" tabindex="-1" role="dialog" aria-labelledby="descriptionModalLabel{{ $transaction->id }}" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="descriptionModalLabel{{ $transaction->id }}">{{ _lang('Transaction Description') }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>{{ $transaction->description }}</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Close') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="card-footer py-4">
                            <nav aria-label="...">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <p class="text-sm text-muted">
                                            {{ _lang('Showing') }} {{ $transactions->firstItem() ?? 0 }} {{ _lang('to') }} {{ $transactions->lastItem() ?? 0 }} {{ _lang('of') }} {{ $transactions->total() }} {{ _lang('transactions') }}
                                        </p>
                                    </div>
                                    <div class="col-sm-6">
                                        {{ $transactions->links() }}
                                    </div>
                                </div>
                            </nav>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">{{ _lang('No transactions found') }}</h4>
                            <p class="text-muted">{{ _lang('No transactions have been recorded for this meeting yet.') }}</p>
                            <div class="mt-3">
                                <a href="{{ route('vsla.meetings.index') }}" class="btn btn-outline-secondary mr-2">
                                    <i class="fa fa-arrow-left"></i> {{ _lang('Back to Meetings') }}
                                </a>
                                @if(auth()->user()->user_type === 'admin' || auth()->user()->user_type === 'superadmin')
                                <a href="{{ route('vsla.transactions.bulk_create', ['meeting_id' => $meeting->id]) }}" class="btn btn-primary">
                                    <i class="fa fa-plus"></i> {{ _lang('Add Transactions') }}
                                </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-refresh every 30 seconds to show real-time updates
    setInterval(function() {
        if (!document.hidden) {
            location.reload();
        }
    }, 30000);
});
</script>
@endpush
