<form action="{{ route('assets.update', $asset) }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="name">{{ _lang('Asset Name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                       id="name" name="name" value="{{ old('name', $asset->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="asset_code">{{ _lang('Asset Code') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('asset_code') is-invalid @enderror" 
                       id="asset_code" name="asset_code" value="{{ old('asset_code', $asset->asset_code) }}" required>
                @error('asset_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="category_id">{{ _lang('Category') }} <span class="text-danger">*</span></label>
                <select class="form-control @error('category_id') is-invalid @enderror" 
                        id="category_id" name="category_id" required>
                    <option value="">{{ _lang('Select Category') }}</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $asset->category_id) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }} ({{ ucfirst($category->type) }})
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="status">{{ _lang('Status') }} <span class="text-danger">*</span></label>
                <select class="form-control @error('status') is-invalid @enderror" 
                        id="status" name="status" required>
                    <option value="active" {{ old('status', $asset->status) === 'active' ? 'selected' : '' }}>
                        {{ _lang('Active') }}
                    </option>
                    <option value="inactive" {{ old('status', $asset->status) === 'inactive' ? 'selected' : '' }}>
                        {{ _lang('Inactive') }}
                    </option>
                    <option value="maintenance" {{ old('status', $asset->status) === 'maintenance' ? 'selected' : '' }}>
                        {{ _lang('Under Maintenance') }}
                    </option>
                    <option value="disposed" {{ old('status', $asset->status) === 'disposed' ? 'selected' : '' }}>
                        {{ _lang('Disposed') }}
                    </option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="description">{{ _lang('Description') }}</label>
        <textarea class="form-control @error('description') is-invalid @enderror" 
                  id="description" name="description" rows="3">{{ old('description', $asset->description) }}</textarea>
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="purchase_value">{{ _lang('Purchase Value') }} <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control @error('purchase_value') is-invalid @enderror" 
                       id="purchase_value" name="purchase_value" value="{{ old('purchase_value', $asset->purchase_value) }}" required>
                @error('purchase_value')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="current_value">{{ _lang('Current Value') }}</label>
                <input type="number" step="0.01" class="form-control @error('current_value') is-invalid @enderror" 
                       id="current_value" name="current_value" value="{{ old('current_value', $asset->current_value) }}">
                @error('current_value')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="purchase_date">{{ _lang('Purchase Date') }} <span class="text-danger">*</span></label>
                <input type="date" class="form-control @error('purchase_date') is-invalid @enderror" 
                       id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $asset->purchase_date) }}" required>
                @error('purchase_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="warranty_expiry">{{ _lang('Warranty Expiry') }}</label>
                <input type="date" class="form-control @error('warranty_expiry') is-invalid @enderror" 
                       id="warranty_expiry" name="warranty_expiry" value="{{ old('warranty_expiry', $asset->warranty_expiry) }}">
                @error('warranty_expiry')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="location">{{ _lang('Location') }}</label>
        <input type="text" class="form-control @error('location') is-invalid @enderror" 
               id="location" name="location" value="{{ old('location', $asset->location) }}">
        @error('location')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="is_leasable" name="is_leasable" value="1" 
                           {{ old('is_leasable', $asset->is_leasable) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_leasable">
                        {{ _lang('Is Leasable') }}
                    </label>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="form-group">
                <label for="lease_rate_type">{{ _lang('Lease Rate Type') }}</label>
                <select class="form-control @error('lease_rate_type') is-invalid @enderror" 
                        id="lease_rate_type" name="lease_rate_type">
                    <option value="daily" {{ old('lease_rate_type', $asset->lease_rate_type) === 'daily' ? 'selected' : '' }}>
                        {{ _lang('Daily') }}
                    </option>
                    <option value="weekly" {{ old('lease_rate_type', $asset->lease_rate_type) === 'weekly' ? 'selected' : '' }}>
                        {{ _lang('Weekly') }}
                    </option>
                    <option value="monthly" {{ old('lease_rate_type', $asset->lease_rate_type) === 'monthly' ? 'selected' : '' }}>
                        {{ _lang('Monthly') }}
                    </option>
                </select>
                @error('lease_rate_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="form-group" id="lease_rate_group" style="{{ old('is_leasable', $asset->is_leasable) ? '' : 'display: none;' }}">
        <label for="lease_rate">{{ _lang('Lease Rate') }}</label>
        <input type="number" step="0.01" class="form-control @error('lease_rate') is-invalid @enderror" 
               id="lease_rate" name="lease_rate" value="{{ old('lease_rate', $asset->lease_rate) }}">
        @error('lease_rate')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="notes">{{ _lang('Notes') }}</label>
        <textarea class="form-control @error('notes') is-invalid @enderror" 
                  id="notes" name="notes" rows="3">{{ old('notes', $asset->notes) }}</textarea>
        @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</form>

<script>
$(document).ready(function() {
    $('#is_leasable').change(function() {
        if ($(this).is(':checked')) {
            $('#lease_rate_group').show();
        } else {
            $('#lease_rate_group').hide();
        }
    });
});
</script>
