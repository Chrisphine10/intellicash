@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ _lang('API Management') }}</h4>
                    <div class="card-tools">
                        <a href="{{ route('api.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> {{ _lang('Create API Key') }}
                        </a>
                        <a href="{{ route('api.documentation') }}" class="btn btn-info btn-sm">
                            <i class="fas fa-book"></i> {{ _lang('API Documentation') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-key"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ _lang('Total API Keys') }}</span>
                                    <span class="info-box-number">{{ $stats['total_keys'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ _lang('Active Keys') }}</span>
                                    <span class="info-box-number">{{ $stats['active_keys'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-building"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ _lang('Tenant Keys') }}</span>
                                    <span class="info-box-number">{{ $stats['tenant_keys'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-users"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">{{ _lang('Member Keys') }}</span>
                                    <span class="info-box-number">{{ $stats['member_keys'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Keys Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="api-keys-table">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Name') }}</th>
                                    <th>{{ _lang('Type') }}</th>
                                    <th>{{ _lang('API Key') }}</th>
                                    <th>{{ _lang('Permissions') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Last Used') }}</th>
                                    <th>{{ _lang('Expires') }}</th>
                                    <th>{{ _lang('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($apiKeys as $apiKey)
                                <tr>
                                    <td>
                                        <strong>{{ $apiKey->name }}</strong>
                                        @if($apiKey->description)
                                        <br><small class="text-muted">{{ $apiKey->description }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $apiKey->type === 'tenant' ? 'primary' : 'warning' }}">
                                            {{ ucfirst($apiKey->type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <code>{{ $apiKey->key }}</code>
                                        <button class="btn btn-sm btn-outline-secondary ml-1" onclick="copyToClipboard('{{ $apiKey->key }}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                    <td>
                                        @foreach($apiKey->permissions as $permission)
                                        <span class="badge badge-secondary">{{ $permission }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if($apiKey->isActive())
                                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                                        @else
                                        <span class="badge badge-danger">{{ _lang('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($apiKey->last_used_at)
                                        {{ $apiKey->last_used_at->diffForHumans() }}
                                        @else
                                        <span class="text-muted">{{ _lang('Never') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($apiKey->expires_at)
                                        {{ $apiKey->expires_at->format('Y-m-d H:i') }}
                                        @else
                                        <span class="text-muted">{{ _lang('Never') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('api.show', $apiKey->id) }}" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('api.edit', $apiKey->id) }}" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($apiKey->is_active)
                                            <button class="btn btn-danger btn-sm" onclick="revokeApiKey({{ $apiKey->id }})">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            @endif
                                            <button class="btn btn-secondary btn-sm" onclick="regenerateSecret({{ $apiKey->id }})">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $apiKeys->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revoke Modal -->
<div class="modal fade" id="revokeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Revoke API Key') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>{{ _lang('Are you sure you want to revoke this API key? This action cannot be undone.') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="confirmRevoke">{{ _lang('Revoke') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Regenerate Secret Modal -->
<div class="modal fade" id="regenerateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ _lang('Regenerate API Secret') }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>{{ _lang('Are you sure you want to regenerate the API secret? The old secret will no longer work.') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Cancel') }}</button>
                <button type="button" class="btn btn-warning" id="confirmRegenerate">{{ _lang('Regenerate') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        toastr.success('{{ _lang("Copied to clipboard") }}');
    });
}

function revokeApiKey(id) {
    $('#revokeModal').modal('show');
    $('#confirmRevoke').off('click').on('click', function() {
        $.ajax({
            url: '{{ route("api.revoke", ":id") }}'.replace(':id', id),
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.result === 'success') {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            }
        });
    });
}

function regenerateSecret(id) {
    $('#regenerateModal').modal('show');
    $('#confirmRegenerate').off('click').on('click', function() {
        $.ajax({
            url: '{{ route("api.regenerate-secret", ":id") }}'.replace(':id', id),
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.result === 'success') {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            }
        });
    });
}
</script>
@endpush
