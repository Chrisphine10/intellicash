@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Meeting Details') }} - {{ $meeting->meeting_number }}</h4>
                <div class="card-tools">
                    <a href="{{ route('vsla.meetings.edit', $meeting->id) }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-edit"></i> {{ _lang('Edit Meeting') }}
                    </a>
                    <a href="{{ route('vsla.meetings.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> {{ _lang('Back to Meetings') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">{{ _lang('Meeting Number') }}:</th>
                                <td>{{ $meeting->meeting_number }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Meeting Date') }}:</th>
                                <td>{{ $meeting->meeting_date->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Meeting Time') }}:</th>
                                <td>{{ $meeting->meeting_time->format('H:i') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Status') }}:</th>
                                <td>
                                    @switch($meeting->status)
                                        @case('scheduled')
                                            <span class="badge badge-info">{{ _lang('Scheduled') }}</span>
                                            @break
                                        @case('in_progress')
                                            <span class="badge badge-warning">{{ _lang('In Progress') }}</span>
                                            @break
                                        @case('completed')
                                            <span class="badge badge-success">{{ _lang('Completed') }}</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge badge-danger">{{ _lang('Cancelled') }}</span>
                                            @break
                                        @default
                                            <span class="badge badge-secondary">{{ ucfirst($meeting->status) }}</span>
                                    @endswitch
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="30%">{{ _lang('Created By') }}:</th>
                                <td>{{ $meeting->createdUser->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Created At') }}:</th>
                                <td>{{ $meeting->created_at->format('d M Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Updated At') }}:</th>
                                <td>{{ $meeting->updated_at->format('d M Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                @if($meeting->agenda)
                <div class="row mt-3">
                    <div class="col-12">
                        <h5>{{ _lang('Agenda') }}</h5>
                        <p class="text-muted">{{ $meeting->agenda }}</p>
                    </div>
                </div>
                @endif
                
                @if($meeting->notes)
                <div class="row mt-3">
                    <div class="col-12">
                        <h5>{{ _lang('Notes') }}</h5>
                        <p class="text-muted">{{ $meeting->notes }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($meeting->attendance->count() > 0)
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Meeting Attendance') }} ({{ $meeting->attendance->count() }} {{ _lang('members') }})</h4>
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
                                        <strong>{{ $attendance->member->first_name }} {{ $attendance->member->last_name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $attendance->member->member_no ?? 'N/A' }}</small>
                                    @else
                                        <span class="text-muted">{{ _lang('Member not found') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($attendance->present)
                                        <span class="badge badge-success">
                                            <i class="fa fa-check"></i> {{ _lang('Present') }}
                                        </span>
                                    @else
                                        <span class="badge badge-danger">
                                            <i class="fa fa-times"></i> {{ _lang('Absent') }}
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $attendance->notes ?? '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <strong>{{ _lang('Attendance Summary') }}:</strong><br>
                            {{ _lang('Present') }}: {{ $meeting->attendance->where('present', true)->count() }}<br>
                            {{ _lang('Absent') }}: {{ $meeting->attendance->where('present', false)->count() }}
                        </div>
                    </div>
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
                <h4 class="card-title">{{ _lang('Meeting Transactions') }} ({{ $meeting->transactions->count() }} {{ _lang('transactions') }})</h4>
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
                                <th>{{ _lang('Date') }}</th>
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
                                        <strong>{{ $transaction->member->first_name }} {{ $transaction->member->last_name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $transaction->member->member_no ?? 'N/A' }}</small>
                                    @else
                                        <span class="text-muted">{{ _lang('Member not found') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ number_format($transaction->amount, 2) }}</strong>
                                </td>
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
                                <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
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
