@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Edit Meeting') }} - {{ $meeting->meeting_number }}</h4>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('vsla.meetings.update', $meeting->id) }}" class="validate">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Date') }}</label>
                                <input type="date" class="form-control" name="meeting_date" value="{{ $meeting->meeting_date->format('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Time') }}</label>
                                <input type="time" class="form-control" name="meeting_time" value="{{ $meeting->meeting_time->format('H:i') }}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Status') }}</label>
                                <select class="form-control" name="status" required>
                                    <option value="scheduled" {{ $meeting->status == 'scheduled' ? 'selected' : '' }}>{{ _lang('Scheduled') }}</option>
                                    <option value="in_progress" {{ $meeting->status == 'in_progress' ? 'selected' : '' }}>{{ _lang('In Progress') }}</option>
                                    <option value="completed" {{ $meeting->status == 'completed' ? 'selected' : '' }}>{{ _lang('Completed') }}</option>
                                    <option value="cancelled" {{ $meeting->status == 'cancelled' ? 'selected' : '' }}>{{ _lang('Cancelled') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Number') }}</label>
                                <input type="text" class="form-control" value="{{ $meeting->meeting_number }}" readonly>
                                <small class="form-text text-muted">{{ _lang('Meeting number cannot be changed') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">{{ _lang('Agenda') }}</label>
                        <textarea class="form-control" name="agenda" rows="3" placeholder="{{ _lang('Enter meeting agenda...') }}">{{ $meeting->agenda }}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">{{ _lang('Notes') }}</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="{{ _lang('Enter meeting notes...') }}">{{ $meeting->notes }}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{ _lang('Update Meeting') }}</button>
                        <a href="{{ route('vsla.meetings.index') }}" class="btn btn-secondary">{{ _lang('Cancel') }}</a>
                        <a href="{{ route('vsla.meetings.show', $meeting->id) }}" class="btn btn-info">{{ _lang('View Meeting') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($meeting->attendance->count() > 0)
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Meeting Attendance') }}</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Member') }}</th>
                                <th>{{ _lang('Present') }}</th>
                                <th>{{ _lang('Notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($meeting->attendance as $attendance)
                            <tr>
                                <td>
                                    @if($attendance->member)
                                        {{ $attendance->member->first_name }} {{ $attendance->member->last_name }}
                                    @else
                                        <span class="text-muted">{{ _lang('Member not found') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->present)
                                        <span class="badge badge-success">{{ _lang('Present') }}</span>
                                    @else
                                        <span class="badge badge-danger">{{ _lang('Absent') }}</span>
                                    @endif
                                </td>
                                <td>{{ $attendance->notes ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@if($meeting->transactions->count() > 0)
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Meeting Transactions') }}</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Member') }}</th>
                                <th>{{ _lang('Amount') }}</th>
                                <th>{{ _lang('Description') }}</th>
                                <th>{{ _lang('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($meeting->transactions as $transaction)
                            <tr>
                                <td>
                                    @switch($transaction->transaction_type)
                                        @case('share_purchase')
                                            <span class="badge badge-info">{{ _lang('Share Purchase') }}</span>
                                            @break
                                        @case('loan_issuance')
                                            <span class="badge badge-warning">{{ _lang('Loan Issuance') }}</span>
                                            @break
                                        @case('loan_repayment')
                                            <span class="badge badge-success">{{ _lang('Loan Repayment') }}</span>
                                            @break
                                        @case('welfare_contribution')
                                            <span class="badge badge-primary">{{ _lang('Welfare Contribution') }}</span>
                                            @break
                                        @default
                                            <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $transaction->transaction_type)) }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    @if($transaction->member)
                                        {{ $transaction->member->first_name }} {{ $transaction->member->last_name }}
                                    @else
                                        <span class="text-muted">{{ _lang('Member not found') }}</span>
                                    @endif
                                </td>
                                <td>{{ number_format($transaction->amount, 2) }}</td>
                                <td>{{ $transaction->description }}</td>
                                <td>
                                    @switch($transaction->status)
                                        @case('pending')
                                            <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                            @break
                                        @case('approved')
                                            <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                            @break
                                        @case('rejected')
                                            <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                            @break
                                        @default
                                            <span class="badge badge-secondary">{{ ucfirst($transaction->status) }}</span>
                                    @endswitch
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
