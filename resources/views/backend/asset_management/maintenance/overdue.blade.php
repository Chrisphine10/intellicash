@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title text-warning">
                    <i class="fas fa-exclamation-triangle"></i> {{ _lang('Overdue Maintenance') }}
                </h4>
                <div>
                    <a href="{{ route('asset-maintenance.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Maintenance') }}
                    </a>
                    <a href="{{ route('asset-maintenance.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Schedule Maintenance') }}
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                @if($maintenance->count() > 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        {{ _lang('The following maintenance tasks are overdue and require immediate attention.') }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Asset') }}</th>
                                    <th>{{ _lang('Type') }}</th>
                                    <th>{{ _lang('Title') }}</th>
                                    <th>{{ _lang('Scheduled Date') }}</th>
                                    <th>{{ _lang('Days Overdue') }}</th>
                                    <th>{{ _lang('Cost') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($maintenance as $record)
                                <tr class="{{ $record->scheduled_date < now()->subDays(7) ? 'table-danger' : 'table-warning' }}">
                                    <td>
                                        <div>
                                            <strong>{{ $record->asset->name }}</strong>
                                            <br><small class="text-muted">{{ $record->asset->asset_code }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ ucfirst($record->maintenance_type) }}</span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $record->title }}</strong>
                                            @if($record->description)
                                                <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($record->description, 50) }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $record->scheduled_date }}</td>
                                    <td>
                                        <span class="badge badge-danger">
                                            {{ \Carbon\Carbon::parse($record->scheduled_date)->diffInDays(now()) }} {{ _lang('days') }}
                                        </span>
                                    </td>
                                    <td>{{ formatAmount($record->cost) }}</td>
                                    <td>
                                        <span class="badge badge-warning">{{ _lang('Overdue') }}</span>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                {{ _lang('Actions') }}
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="{{ route('asset-maintenance.show', $record) }}">
                                                    <i class="fas fa-eye"></i> {{ _lang('View') }}
                                                </a>
                                                <a class="dropdown-item" href="{{ route('asset-maintenance.edit', $record) }}">
                                                    <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('asset-maintenance.mark-in-progress', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="fas fa-play"></i> {{ _lang('Start Now') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('asset-maintenance.complete', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="fas fa-check"></i> {{ _lang('Mark Complete') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('asset-maintenance.cancel', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">{{ _lang('No Overdue Maintenance') }}</h5>
                        <p class="text-muted">{{ _lang('All maintenance tasks are up to date!') }}</p>
                        <a href="{{ route('asset-maintenance.index') }}" class="btn btn-primary">
                            {{ _lang('View All Maintenance') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

