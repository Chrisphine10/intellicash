@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-management.dashboard') }}">{{ _lang('Asset Management') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-categories.index') }}">{{ _lang('Categories') }}</a></li>
                        <li class="breadcrumb-item active">{{ $asset_category->name }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Asset Category Details') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ $asset_category->name }}</h4>
                        <div>
                            <a href="{{ route('asset-categories.edit', $asset_category) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit me-1"></i> {{ _lang('Edit') }}
                            </a>
                            <a href="{{ route('asset-categories.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> {{ _lang('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ _lang('Category Information') }}</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Name') }}:</strong></td>
                                    <td>{{ $asset_category->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Type') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-{{ $asset_category->type === 'fixed' ? 'primary' : ($asset_category->type === 'investment' ? 'success' : 'info') }}">
                                            {{ ucfirst($asset_category->type) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        @if($asset_category->is_active)
                                            <span class="badge badge-success">{{ _lang('Active') }}</span>
                                        @else
                                            <span class="badge badge-danger">{{ _lang('Inactive') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Assets') }}:</strong></td>
                                    <td>{{ $asset_category->assets_count ?? $asset_category->assets->count() }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ _lang('Description') }}</h6>
                            <p class="text-muted">
                                {{ $asset_category->description ?: _lang('No description provided') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assets in this Category -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Assets in this Category') }}</h4>
                </div>
                <div class="card-body">
                    @if($asset_category->assets->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Asset Code') }}</th>
                                        <th>{{ _lang('Name') }}</th>
                                        <th>{{ _lang('Value') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                        <th>{{ _lang('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($asset_category->assets as $asset)
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
                                        <td>{{ formatAmount($asset->purchase_value) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">
                                                {{ ucfirst($asset->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('assets.show', ['tenant' => app('tenant')->slug, 'asset' => $asset]) }}" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No assets in this category') }}</h5>
                            <p class="text-muted">{{ _lang('Assets will appear here once they are added to this category.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Category Statistics') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-primary">{{ $asset_category->assets->count() }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Total Assets') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success">{{ formatAmount($asset_category->assets->sum('purchase_value')) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Total Value') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Leases -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Active Leases') }}</h4>
                </div>
                <div class="card-body">
                    @php
                        $activeLeases = $asset_category->assets->flatMap(function($asset) {
                            return $asset->activeLeases;
                        });
                    @endphp
                    
                    @if($activeLeases->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($activeLeases as $lease)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $lease->asset->name }}</h6>
                                        <p class="mb-1 text-muted">{{ _lang('Member') }}: {{ $lease->member->first_name }} {{ $lease->member->last_name }}</p>
                                        <small class="text-muted">
                                            {{ _lang('Rate') }}: {{ formatAmount($lease->daily_rate) }}/{{ _lang('day') }}
                                        </small>
                                    </div>
                                    <span class="badge badge-success">{{ _lang('Active') }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-handshake fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">{{ _lang('No active leases') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
