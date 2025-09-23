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
                        <li class="breadcrumb-item"><a href="{{ route('asset-maintenance.index') }}">{{ _lang('Maintenance') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Create Maintenance') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Create New Maintenance') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Maintenance Information') }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('asset-maintenance.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asset_id">{{ _lang('Asset') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('asset_id') is-invalid @enderror" 
                                            id="asset_id" name="asset_id" required>
                                        <option value="">{{ _lang('Select Asset') }}</option>
                                        @foreach(\App\Models\Asset::where('tenant_id', app('tenant')->id)->get() as $asset)
                                            <option value="{{ $asset->id }}" {{ old('asset_id', request('asset_id')) == $asset->id ? 'selected' : '' }}>
                                                {{ $asset->name }} ({{ $asset->asset_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('asset_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="maintenance_type">{{ _lang('Maintenance Type') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('maintenance_type') is-invalid @enderror" 
                                            id="maintenance_type" name="maintenance_type" required>
                                        <option value="">{{ _lang('Select Type') }}</option>
                                        <option value="scheduled" {{ old('maintenance_type') === 'scheduled' ? 'selected' : '' }}>
                                            {{ _lang('Scheduled') }}
                                        </option>
                                        <option value="emergency" {{ old('maintenance_type') === 'emergency' ? 'selected' : '' }}>
                                            {{ _lang('Emergency') }}
                                        </option>
                                        <option value="repair" {{ old('maintenance_type') === 'repair' ? 'selected' : '' }}>
                                            {{ _lang('Repair') }}
                                        </option>
                                        <option value="inspection" {{ old('maintenance_type') === 'inspection' ? 'selected' : '' }}>
                                            {{ _lang('Inspection') }}
                                        </option>
                                    </select>
                                    @error('maintenance_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="title">{{ _lang('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                   id="title" name="title" value="{{ old('title') }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">{{ _lang('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scheduled_date">{{ _lang('Scheduled Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" 
                                           id="scheduled_date" name="scheduled_date" value="{{ old('scheduled_date') }}" required>
                                    @error('scheduled_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cost">{{ _lang('Estimated Cost') }}</label>
                                    <input type="number" step="0.01" class="form-control @error('cost') is-invalid @enderror" 
                                           id="cost" name="cost" value="{{ old('cost') }}">
                                    @error('cost')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="performed_by">{{ _lang('Performed By') }}</label>
                            <input type="text" class="form-control @error('performed_by') is-invalid @enderror" 
                                   id="performed_by" name="performed_by" value="{{ old('performed_by') }}">
                            @error('performed_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="notes">{{ _lang('Notes') }}</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ _lang('Create Maintenance') }}
                            </button>
                            <a href="{{ route('asset-maintenance.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> {{ _lang('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Maintenance Types') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ _lang('Scheduled') }}</h6>
                        <p class="text-muted small">{{ _lang('Regular maintenance tasks planned in advance') }}</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-warning">{{ _lang('Emergency') }}</h6>
                        <p class="text-muted small">{{ _lang('Urgent repairs that need immediate attention') }}</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-danger">{{ _lang('Repair') }}</h6>
                        <p class="text-muted small">{{ _lang('Fixing broken or damaged equipment') }}</p>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-info">{{ _lang('Inspection') }}</h6>
                        <p class="text-muted small">{{ _lang('Regular checks to ensure proper functioning') }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Available Assets') }}</h4>
                </div>
                <div class="card-body">
                    @php
                        $availableAssets = \App\Models\Asset::where('tenant_id', app('tenant')->id)->get();
                    @endphp
                    
                    @if($availableAssets->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($availableAssets->take(5) as $asset)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $asset->name }}</h6>
                                        <p class="mb-1 text-muted">{{ $asset->asset_code }}</p>
                                        <small class="text-muted">{{ $asset->category->name }}</small>
                                    </div>
                                    <span class="badge badge-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($asset->status) }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @if($availableAssets->count() > 5)
                            <div class="text-center mt-2">
                                <small class="text-muted">{{ _lang('And') }} {{ $availableAssets->count() - 5 }} {{ _lang('more assets') }}</small>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-building fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">{{ _lang('No assets available') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
