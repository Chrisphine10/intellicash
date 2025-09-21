@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ _lang('Create API Key') }}</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('api.store') }}" id="api-key-form">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">{{ _lang('API Key Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="type">{{ _lang('Type') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" id="type" name="type" required>
                                        <option value="tenant" {{ old('type') == 'tenant' ? 'selected' : '' }}>{{ _lang('Tenant API Key') }}</option>
                                        <option value="member" {{ old('type') == 'member' ? 'selected' : '' }}>{{ _lang('Member API Key') }}</option>
                                    </select>
                                    @error('type')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row" id="member-selection" style="display: none;">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="member_id">{{ _lang('Member') }} <span class="text-danger">*</span></label>
                                    <select class="form-control" id="member_id" name="member_id">
                                        <option value="">{{ _lang('Select Member') }}</option>
                                        @foreach($members as $member)
                                        <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>
                                            {{ $member->name }} ({{ $member->member_no }})
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('member_id')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{ _lang('Permissions') }} <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="read" id="perm_read" {{ in_array('read', old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="perm_read">
                                                    {{ _lang('Read') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="write" id="perm_write" {{ in_array('write', old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="perm_write">
                                                    {{ _lang('Write') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="delete" id="perm_delete" {{ in_array('delete', old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="perm_delete">
                                                    {{ _lang('Delete') }}
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="admin" id="perm_admin" {{ in_array('admin', old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="perm_admin">
                                                    {{ _lang('Admin') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2" id="member-permissions" style="display: none;">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="own_data" id="perm_own_data" {{ in_array('own_data', old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="perm_own_data">
                                                    {{ _lang('Own Data Only') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    @error('permissions')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="expires_at">{{ _lang('Expires At') }}</label>
                                    <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" value="{{ old('expires_at') }}">
                                    @error('expires_at')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rate_limit">{{ _lang('Rate Limit (requests per hour)') }}</label>
                                    <input type="number" class="form-control" id="rate_limit" name="rate_limit" value="{{ old('rate_limit', 1000) }}" min="1" max="10000">
                                    @error('rate_limit')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="ip_whitelist">{{ _lang('IP Whitelist (comma-separated)') }}</label>
                                    <input type="text" class="form-control" id="ip_whitelist" name="ip_whitelist" value="{{ old('ip_whitelist') }}" placeholder="192.168.1.1, 10.0.0.1">
                                    <small class="form-text text-muted">{{ _lang('Leave empty to allow all IPs') }}</small>
                                    @error('ip_whitelist')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description">{{ _lang('Description') }}</label>
                                    <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                    @error('description')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">{{ _lang('Create API Key') }}</button>
                            <a href="{{ route('api.index') }}" class="btn btn-secondary">{{ _lang('Cancel') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ _lang('API Key Types') }}</h5>
                </div>
                <div class="card-body">
                    <h6>{{ _lang('Tenant API Key') }}</h6>
                    <p class="text-muted">{{ _lang('Full access to all organization data and operations. Use for system integrations.') }}</p>
                    
                    <h6>{{ _lang('Member API Key') }}</h6>
                    <p class="text-muted">{{ _lang('Limited access to specific member data. Use for member portal integrations.') }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">{{ _lang('Permissions') }}</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li><strong>{{ _lang('Read') }}:</strong> {{ _lang('View data') }}</li>
                        <li><strong>{{ _lang('Write') }}:</strong> {{ _lang('Create and update data') }}</li>
                        <li><strong>{{ _lang('Delete') }}:</strong> {{ _lang('Delete data') }}</li>
                        <li><strong>{{ _lang('Admin') }}:</strong> {{ _lang('Full administrative access') }}</li>
                        <li><strong>{{ _lang('Own Data Only') }}:</strong> {{ _lang('Access only own data (Member keys)') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#type').change(function() {
        if ($(this).val() === 'member') {
            $('#member-selection').show();
            $('#member-permissions').show();
            $('#member_id').prop('required', true);
            $('#rate_limit').val(100);
        } else {
            $('#member-selection').hide();
            $('#member-permissions').hide();
            $('#member_id').prop('required', false);
            $('#rate_limit').val(1000);
        }
    });

    // Trigger change event on page load
    $('#type').trigger('change');
});
</script>
@endpush
