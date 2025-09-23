@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ _lang('Asset Maintenance') }}</h4>
                <div>
                    <a href="{{ route('asset-maintenance.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Schedule Maintenance') }}
                    </a>
                    <a href="{{ route('asset-maintenance.overdue') }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-exclamation-triangle"></i> {{ _lang('Overdue') }}
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <form method="GET" action="{{ route('asset-maintenance.index') }}">
                            <div class="row">
                                <div class="col-md-2">
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Status') }}</option>
                                        <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>{{ _lang('Scheduled') }}</option>
                                        <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>{{ _lang('In Progress') }}</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ _lang('Completed') }}</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ _lang('Cancelled') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="maintenance_type" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Types') }}</option>
                                        <option value="scheduled" {{ request('maintenance_type') == 'scheduled' ? 'selected' : '' }}>{{ _lang('Scheduled') }}</option>
                                        <option value="emergency" {{ request('maintenance_type') == 'emergency' ? 'selected' : '' }}>{{ _lang('Emergency') }}</option>
                                        <option value="repair" {{ request('maintenance_type') == 'repair' ? 'selected' : '' }}>{{ _lang('Repair') }}</option>
                                        <option value="inspection" {{ request('maintenance_type') == 'inspection' ? 'selected' : '' }}>{{ _lang('Inspection') }}</option>
                                        <option value="cleaning" {{ request('maintenance_type') == 'cleaning' ? 'selected' : '' }}>{{ _lang('Cleaning') }}</option>
                                        <option value="upgrade" {{ request('maintenance_type') == 'upgrade' ? 'selected' : '' }}>{{ _lang('Upgrade') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="asset_id" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Assets') }}</option>
                                        @foreach($assets as $asset)
                                            <option value="{{ $asset->id }}" {{ request('asset_id') == $asset->id ? 'selected' : '' }}>
                                                {{ $asset->name }} ({{ $asset->asset_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="date_from" class="form-control form-control-sm" 
                                           placeholder="{{ _lang('From Date') }}" 
                                           value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" name="date_to" class="form-control form-control-sm" 
                                           placeholder="{{ _lang('To Date') }}" 
                                           value="{{ request('date_to') }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="{{ _lang('Search...') }}" 
                                           value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i> {{ _lang('Filter') }}
                                    </button>
                                    <a href="{{ route('asset-maintenance.index') }}" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-refresh"></i> {{ _lang('Reset') }}
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Maintenance Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ _lang('Asset') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Title') }}</th>
                                <th>{{ _lang('Scheduled Date') }}</th>
                                <th>{{ _lang('Cost') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Created By') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($maintenance as $record)
                            <tr>
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
                                <td>{{ formatAmount($record->cost) }}</td>
                                <td>
                                    @if($record->status == 'scheduled')
                                        <span class="badge badge-secondary">{{ _lang('Scheduled') }}</span>
                                    @elseif($record->status == 'in_progress')
                                        <span class="badge badge-warning">{{ _lang('In Progress') }}</span>
                                    @elseif($record->status == 'completed')
                                        <span class="badge badge-success">{{ _lang('Completed') }}</span>
                                    @elseif($record->status == 'cancelled')
                                        <span class="badge badge-danger">{{ _lang('Cancelled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $record->createdBy->name ?? _lang('System') }}
                                    <br><small class="text-muted">{{ $record->created_at->format('M d, Y') }}</small>
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
                                            @if($record->status !== 'completed')
                                                <a class="dropdown-item" href="{{ route('asset-maintenance.edit', $record) }}">
                                                    <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                                </a>
                                            @endif
                                            @if($record->status == 'scheduled')
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('asset-maintenance.mark-in-progress', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="fas fa-play"></i> {{ _lang('Start') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('asset-maintenance.complete', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="fas fa-check"></i> {{ _lang('Complete') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('asset-maintenance.cancel', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                                    </button>
                                                </form>
                                            @elseif($record->status == 'in_progress')
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('asset-maintenance.complete', $record) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="fas fa-check"></i> {{ _lang('Complete') }}
                                                    </button>
                                                </form>
                                            @endif
                                            @if($record->status !== 'completed')
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('asset-maintenance.destroy', $record) }}" method="POST" class="d-inline delete-form">
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
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $maintenance->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    $('.delete-form').on('submit', function(e) {
        e.preventDefault();
        if (confirm('{{ _lang("Are you sure you want to delete this maintenance record?") }}')) {
            this.submit();
        }
    });
});
</script>
@endsection

