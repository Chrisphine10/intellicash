@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('VSLA Meetings') }}</h4>
                <a href="{{ route('vsla.meetings.create') }}" class="btn btn-primary btn-sm float-right">
                    <i class="fas fa-plus"></i> {{ _lang('New Meeting') }}
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Meeting Number') }}</th>
                                <th>{{ _lang('Date') }}</th>
                                <th>{{ _lang('Time') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Attendance') }}</th>
                                <th>{{ _lang('Created By') }}</th>
                                <th>{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($meetings as $meeting)
                            <tr>
                                <td>{{ $meeting->meeting_number }}</td>
                                <td>{{ $meeting->meeting_date }}</td>
                                <td>{{ $meeting->meeting_time }}</td>
                                <td>
                                    <span class="badge badge-{{ $meeting->status == 'completed' ? 'success' : ($meeting->status == 'in_progress' ? 'warning' : ($meeting->status == 'cancelled' ? 'danger' : 'info')) }}">
                                        {{ ucfirst($meeting->status) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $meeting->attendance->where('present', true)->count() }} / {{ $meeting->attendance->count() }}
                                </td>
                                <td>{{ $meeting->createdUser ? $meeting->createdUser->name : 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('vsla.meetings.show', $meeting->id) }}" class="btn btn-info btn-sm" title="{{ _lang('View Meeting') }}">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('vsla.meetings.edit', $meeting->id) }}" class="btn btn-warning btn-sm" title="{{ _lang('Edit Meeting') }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="{{ route('vsla.transactions.bulk_create', ['meeting_id' => $meeting->id]) }}" class="btn btn-success btn-sm" title="{{ _lang('Bulk Transactions') }}">
                                        <i class="fas fa-list"></i>
                                    </a>
                                    <form method="POST" action="{{ route('vsla.meetings.destroy', $meeting->id) }}" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ _lang("Are you sure?") }}')" title="{{ _lang('Delete Meeting') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-center">
                    {{ $meetings->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
