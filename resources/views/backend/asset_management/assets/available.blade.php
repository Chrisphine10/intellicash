@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ _lang('Assets Available for Lease') }}</h4>
                <div>
                    <a href="{{ route('assets.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Assets') }}
                    </a>
                    <a href="{{ route('asset-leases.create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> {{ _lang('Create Lease') }}
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                @if($assets->count() > 0)
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        {{ _lang('The following assets are available for lease. Click on an asset to create a new lease.') }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Asset Code') }}</th>
                                    <th>{{ _lang('Name') }}</th>
                                    <th>{{ _lang('Category') }}</th>
                                    <th>{{ _lang('Current Value') }}</th>
                                    <th>{{ _lang('Lease Rate') }}</th>
                                    <th>{{ _lang('Location') }}</th>
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
                                    <td>{{ formatAmount($asset->current_value) }}</td>
                                    <td>
                                        <strong>{{ formatAmount($asset->lease_rate) }}</strong>
                                        <br><small class="text-muted">/ {{ ucfirst($asset->lease_rate_type) }}</small>
                                    </td>
                                    <td>{{ $asset->location ?? _lang('Not specified') }}</td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                {{ _lang('Actions') }}
                                            </button>
                                            <div class="dropdown-menu">
                                                <a class="dropdown-item" href="{{ route('assets.show', $asset) }}">
                                                    <i class="fas fa-eye"></i> {{ _lang('View Details') }}
                                                </a>
                                                <a class="dropdown-item" href="{{ route('assets.lease-form', $asset) }}">
                                                    <i class="fas fa-handshake"></i> {{ _lang('Create Lease') }}
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <a class="dropdown-item" href="{{ route('assets.edit', $asset) }}">
                                                    <i class="fas fa-edit"></i> {{ _lang('Edit Asset') }}
                                                </a>
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
                        <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">{{ _lang('No Assets Available for Lease') }}</h5>
                        <p class="text-muted">{{ _lang('All leasable assets are currently leased out or there are no leasable assets.') }}</p>
                        <a href="{{ route('assets.index') }}" class="btn btn-primary">
                            {{ _lang('View All Assets') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

