@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Create New Meeting') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('vsla.meetings.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Meetings') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('vsla.meetings.store') }}" class="validate">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Date') }}</label>
                                <input type="date" class="form-control" name="meeting_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Time') }}</label>
                                <input type="time" class="form-control" name="meeting_time" value="10:00" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">{{ _lang('Agenda') }}</label>
                        <textarea class="form-control" name="agenda" rows="3" placeholder="{{ _lang('Enter meeting agenda...') }}"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label">{{ _lang('Member Attendance') }}</label>
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
                                    @foreach($members as $member)
                                    <tr>
                                        <td>{{ $member->first_name }} {{ $member->last_name }}</td>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="attendance[{{ $loop->index }}][present]" value="1">
                                                <input type="hidden" name="attendance[{{ $loop->index }}][member_id]" value="{{ $member->id }}">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control" name="attendance[{{ $loop->index }}][notes]" placeholder="{{ _lang('Optional notes') }}">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{ _lang('Create Meeting') }}</button>
                        <a href="{{ route('vsla.meetings.index') }}" class="btn btn-secondary">{{ _lang('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
