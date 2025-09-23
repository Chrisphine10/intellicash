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
                        <li class="breadcrumb-item active">{{ _lang('Edit Category') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Edit Asset Category') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Edit Category') }}: {{ $asset_category->name }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('asset-categories.update', $asset_category) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">{{ _lang('Category Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name', $asset_category->name) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="type">{{ _lang('Category Type') }} <span class="text-danger">*</span></label>
                                    <select class="form-control @error('type') is-invalid @enderror" 
                                            id="type" name="type" required>
                                        <option value="fixed" {{ old('type', $asset_category->type) === 'fixed' ? 'selected' : '' }}>
                                            {{ _lang('Fixed Assets') }}
                                        </option>
                                        <option value="investment" {{ old('type', $asset_category->type) === 'investment' ? 'selected' : '' }}>
                                            {{ _lang('Investment Assets') }}
                                        </option>
                                        <option value="leasable" {{ old('type', $asset_category->type) === 'leasable' ? 'selected' : '' }}>
                                            {{ _lang('Leasable Assets') }}
                                        </option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">{{ _lang('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" name="description" rows="4">{{ old('description', $asset_category->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       value="1" {{ old('is_active', $asset_category->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    {{ _lang('Active') }}
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> {{ _lang('Update Category') }}
                            </button>
                            <a href="{{ route('asset-categories.show', $asset_category) }}" class="btn btn-secondary">
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
                    <h4 class="card-title mb-0">{{ _lang('Category Information') }}</h4>
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

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Category Types') }}</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">{{ _lang('Fixed Assets') }}</h6>
                        <p class="text-muted small">{{ _lang('Office equipment, buildings, machinery that are not leased out') }}</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-success">{{ _lang('Investment Assets') }}</h6>
                        <p class="text-muted small">{{ _lang('Government bonds, mutual funds, stocks, and other investments') }}</p>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-info">{{ _lang('Leasable Assets') }}</h6>
                        <p class="text-muted small">{{ _lang('Vehicles, equipment, tents that can be leased to members') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
