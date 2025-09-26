@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <span class="panel-title">{{ _lang('My Lease Requests') }}</span>
                    <a class="btn btn-primary btn-sm float-right" href="{{ route('lease-requests.member.create') }}">
                        <i class="fas fa-plus"></i> {{ _lang('New Lease Request') }}
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
                                <button type="submit" class="btn btn-primary">{{ _lang('Filter') }}</button>
                                <a href="{{ route('lease-requests.member.index') }}" class="btn btn-secondary">{{ _lang('Clear') }}</a>
                            </div>
                        </div>
                    </form>

                    @if($requests->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Request #') }}</th>
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
                                                <a href="{{ route('lease-requests.member.show', $request) }}" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> {{ _lang('View') }}
                                                </a>
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
@endsection
