@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ _lang('Asset Management') }}</h4>
                <div>
                    <a href="{{ route('asset-categories.index') }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-tags"></i> {{ _lang('Categories') }}
                    </a>
                    <a href="{{ route('asset-leases.index') }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-handshake"></i> {{ _lang('Leases') }}
                    </a>
                    <a href="{{ route('asset-maintenance.index') }}" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-wrench"></i> {{ _lang('Maintenance') }}
                    </a>
                    <a href="{{ route('assets.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Add Asset') }}
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <form method="GET" action="{{ route('assets.index') }}">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="category_id" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Categories') }}</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Status') }}</option>
                                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ _lang('Active') }}</option>
                                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ _lang('Inactive') }}</option>
                                        <option value="maintenance" {{ request('status') == 'maintenance' ? 'selected' : '' }}>{{ _lang('Maintenance') }}</option>
                                        <option value="disposed" {{ request('status') == 'disposed' ? 'selected' : '' }}>{{ _lang('Disposed') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="is_leasable" class="form-control form-control-sm">
                                        <option value="">{{ _lang('All Types') }}</option>
                                        <option value="1" {{ request('is_leasable') == '1' ? 'selected' : '' }}>{{ _lang('Leasable') }}</option>
                                        <option value="0" {{ request('is_leasable') == '0' ? 'selected' : '' }}>{{ _lang('Non-Leasable') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="{{ _lang('Search assets...') }}" 
                                           value="{{ request('search') }}">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i> {{ _lang('Filter') }}
                                    </button>
                                    <a href="{{ route('assets.index') }}" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-refresh"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assets Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ _lang('Asset Code') }}</th>
                                <th>{{ _lang('Name') }}</th>
                                <th>{{ _lang('Category') }}</th>
                                <th>{{ _lang('Purchase Value') }}</th>
                                <th>{{ _lang('Current Value') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Lease Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assets as $asset)
                            <tr>
                                <td>
                                    <strong>{{ $asset->asset_code }}</strong>
                                </td>
                                <td>
                                    <div>
                                        <strong>{{ $asset->name }}</strong>
                                        @if($asset->description)
                                            <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($asset->description, 50) }}</small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">{{ $asset->category->name }}</span>
                                    <br><small class="text-muted">{{ ucfirst($asset->category->type) }}</small>
                                </td>
                                <td>{{ formatAmount($asset->purchase_value) }}</td>
                                <td>{{ formatAmount($asset->current_value) }}</td>
                                <td>
                                    @if($asset->status == 'active')
                                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                                    @elseif($asset->status == 'inactive')
                                        <span class="badge badge-secondary">{{ _lang('Inactive') }}</span>
                                    @elseif($asset->status == 'maintenance')
                                        <span class="badge badge-warning">{{ _lang('Maintenance') }}</span>
                                    @elseif($asset->status == 'disposed')
                                        <span class="badge badge-danger">{{ _lang('Disposed') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($asset->is_leasable)
                                        @if($asset->activeLeases->count() > 0)
                                            <span class="badge badge-info">{{ _lang('Leased') }}</span>
                                            <br><small>{{ _lang('To:') }} {{ $asset->activeLeases->first()->member->first_name }}</small>
                                        @else
                                            <span class="badge badge-success">{{ _lang('Available') }}</span>
                                            <br><small>{{ formatAmount($asset->lease_rate) }}/{{ $asset->lease_rate_type }}</small>
                                        @endif
                                    @else
                                        <span class="badge badge-secondary">{{ _lang('Not Leasable') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            {{ _lang('Actions') }}
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('assets.show', $asset) }}">
                                                <i class="fas fa-eye"></i> {{ _lang('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('assets.edit', $asset) }}">
                                                <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                            </a>
                                            @if($asset->is_leasable && $asset->activeLeases->count() == 0)
                                                <a class="dropdown-item" href="{{ route('assets.lease-form', $asset) }}">
                                                    <i class="fas fa-handshake"></i> {{ _lang('Create Lease') }}
                                                </a>
                                            @endif
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('assets.destroy', $asset) }}" method="POST" class="d-inline delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="fas fa-trash"></i> {{ _lang('Delete') }}
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

                {{ $assets->links() }}
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
        if (confirm('{{ _lang("Are you sure you want to delete this asset?") }}')) {
            this.submit();
        }
    });
});
</script>
@endsection
