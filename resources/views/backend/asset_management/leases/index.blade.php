@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ _lang('Asset Leases') }}</h4>
                <div>
                    <a href="{{ route('asset-leases.create', ['tenant' => app('tenant')->slug]) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Create Lease') }}
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <form method="GET" action="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Status') }}</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ _lang('Active') }}</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ _lang('Completed') }}</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ _lang('Cancelled') }}</option>
                                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>{{ _lang('Overdue') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="member_id" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Members') }}</option>
                                        @foreach($members as $member)
                                            <option value="{{ $member->id }}" {{ request('member_id') == $member->id ? 'selected' : '' }}>
                                                {{ $member->first_name }} {{ $member->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
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
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="{{ _lang('Search leases...') }}" 
                                           value="{{ request('search') }}">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-refresh"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Leases Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ _lang('Lease Number') }}</th>
                                <th>{{ _lang('Asset') }}</th>
                                <th>{{ _lang('Member') }}</th>
                                <th>{{ _lang('Start Date') }}</th>
                                <th>{{ _lang('End Date') }}</th>
                                <th>{{ _lang('Daily Rate') }}</th>
                                <th>{{ _lang('Total Amount') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($leases as $lease)
                            <tr>
                                <td>
                                    <strong>{{ $lease->lease_number }}</strong>
                                </td>
                                <td>
                                    <div>
                                        @if($lease->asset)
                                            <strong>{{ $lease->asset->name }}</strong>
                                            <br><small class="text-muted">{{ $lease->asset->asset_code }}</small>
                                        @else
                                            <strong class="text-muted">{{ _lang('Asset not found') }}</strong>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($lease->member)
                                        {{ $lease->member->first_name }} {{ $lease->member->last_name }}
                                        <br><small class="text-muted">{{ $lease->member->member_code }}</small>
                                    @else
                                        <span class="text-muted">{{ _lang('Member not found') }}</span>
                                    @endif
                                </td>
                                <td>{{ $lease->start_date }}</td>
                                <td>{{ $lease->end_date ?? _lang('Open-ended') }}</td>
                                <td>{{ formatAmount($lease->daily_rate) }}</td>
                                <td>{{ formatAmount($lease->total_amount) }}</td>
                                <td>
                                    @if($lease->status == 'active')
                                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                                    @elseif($lease->status == 'completed')
                                        <span class="badge badge-info">{{ _lang('Completed') }}</span>
                                    @elseif($lease->status == 'cancelled')
                                        <span class="badge badge-danger">{{ _lang('Cancelled') }}</span>
                                    @elseif($lease->status == 'overdue')
                                        <span class="badge badge-warning">{{ _lang('Overdue') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            {{ _lang('Actions') }}
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('asset-leases.show', ['tenant' => app('tenant')->slug, 'asset_lease' => $lease->id ?? 0]) }}">
                                                <i class="fas fa-eye"></i> {{ _lang('View') }}
                                            </a>
                                            @if($lease->status == 'active')
                                                <a class="dropdown-item" href="{{ route('asset-leases.edit', ['tenant' => app('tenant')->slug, 'asset_lease' => $lease->id ?? 0]) }}">
                                                    <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('asset-leases.complete', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="fas fa-check"></i> {{ _lang('Complete') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('asset-leases.cancel', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                                    </button>
                                                </form>
                                                <form action="{{ route('asset-leases.mark-overdue', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="fas fa-exclamation-triangle"></i> {{ _lang('Mark Overdue') }}
                                                    </button>
                                                </form>
                                            @endif
                                            @if($lease->status !== 'completed')
                                                <div class="dropdown-divider"></div>
                                                <form action="{{ route('asset-leases.destroy', ['tenant' => app('tenant')->slug, 'asset_lease' => $lease->id ?? 0]) }}" method="POST" class="d-inline delete-form">
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

                {{ $leases->links() }}
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
        if (confirm('{{ _lang("Are you sure you want to delete this lease?") }}')) {
            this.submit();
        }
    });
});
</script>
@endsection

