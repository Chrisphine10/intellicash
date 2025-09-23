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
                        <li class="breadcrumb-item active">{{ _lang('Edit Maintenance') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Edit Maintenance') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Edit Maintenance') }}: {{ $maintenance->title }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('asset-maintenance.update', $maintenance) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="asset_id">{{ _lang('Asset') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('asset_id') is-invalid @enderror" 
                                            id="asset_id" name="asset_id" required>
                                        <option value="">{{ _lang('Select Asset') }}</option>
                                        @foreach(\App\Models\Asset::where('tenant_id', app('tenant')->id)->get() as $asset)
                                            <option value="{{ $asset->id }}" {{ old('asset_id', $maintenance->asset_id) == $asset->id ? 'selected' : '' }}>
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
                                        <option value="scheduled" {{ old('maintenance_type', $maintenance->maintenance_type) === 'scheduled' ? 'selected' : '' }}>
                                            {{ _lang('Scheduled') }}
                                        </option>
                                        <option value="emergency" {{ old('maintenance_type', $maintenance->maintenance_type) === 'emergency' ? 'selected' : '' }}>
                                            {{ _lang('Emergency') }}
                                        </option>
                                        <option value="repair" {{ old('maintenance_type', $maintenance->maintenance_type) === 'repair' ? 'selected' : '' }}>
                                            {{ _lang('Repair') }}
                                        </option>
                                        <option value="inspection" {{ old('maintenance_type', $maintenance->maintenance_type) === 'inspection' ? 'selected' : '' }}>
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
                                   id="title" name="title" value="{{ old('title', $maintenance->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">{{ _lang('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="3">{{ old('description', $maintenance->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="scheduled_date">{{ _lang('Scheduled Date') }} <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" 
                                           id="scheduled_date" name="scheduled_date" value="{{ old('scheduled_date', $maintenance->scheduled_date) }}" required>
                                    @error('scheduled_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="completed_date">{{ _lang('Completed Date') }}</label>
                                    <input type="date" class="form-control @error('completed_date') is-invalid @enderror" 
                                           id="completed_date" name="completed_date" value="{{ old('completed_date', $maintenance->completed_date) }}">
                                    @error('completed_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cost">{{ _lang('Cost') }}</label>
                                    <input type="number" step="0.01" class="form-control @error('cost') is-invalid @enderror" 
                                           id="cost" name="cost" value="{{ old('cost', $maintenance->cost) }}">
                                    @error('cost')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">{{ _lang('Status') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('status') is-invalid @enderror" 
                                            id="status" name="status" required>
                                        <option value="scheduled" {{ old('status', $maintenance->status) === 'scheduled' ? 'selected' : '' }}>
                                            {{ _lang('Scheduled') }}
                                        </option>
                                        <option value="in_progress" {{ old('status', $maintenance->status) === 'in_progress' ? 'selected' : '' }}>
                                            {{ _lang('In Progress') }}
                                        </option>
                                        <option value="completed" {{ old('status', $maintenance->status) === 'completed' ? 'selected' : '' }}>
                                            {{ _lang('Completed') }}
                                        </option>
                                        <option value="cancelled" {{ old('status', $maintenance->status) === 'cancelled' ? 'selected' : '' }}>
                                            {{ _lang('Cancelled') }}
                                        </option>
                                    </select>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="performed_by">{{ _lang('Performed By') }}</label>
                            <input type="text" class="form-control @error('performed_by') is-invalid @enderror" 
                                   id="performed_by" name="performed_by" value="{{ old('performed_by', $maintenance->performed_by) }}">
                            @error('performed_by')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="notes">{{ _lang('Notes') }}</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3">{{ old('notes', $maintenance->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ _lang('Update Maintenance') }}
                            </button>
                            <a href="{{ route('asset-maintenance.show', $maintenance) }}" class="btn btn-secondary">
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
                    <h4 class="card-title mb-0">{{ _lang('Maintenance Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-primary">{{ $maintenance->asset->maintenance->count() }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Total Maintenance') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success">{{ formatAmount($maintenance->asset->maintenance->sum('cost')) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Total Cost') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Information') }}</h4>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <h5>{{ $maintenance->asset->name }}</h5>
                        <p class="text-muted">{{ $maintenance->asset->asset_code }}</p>
                        <span class="badge badge-secondary">{{ $maintenance->asset->category->name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
