@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Module Management') }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($modules as $key => $module)
                    <div class="col-md-6 col-lg-4">
                        <div class="card module-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center">
                                            @if($key === 'vsla')
                                                <i class="fas fa-users fa-lg"></i>
                                            @elseif($key === 'api')
                                                <i class="fas fa-code fa-lg"></i>
                                            @elseif($key === 'qr_code')
                                                <i class="fas fa-qrcode fa-lg"></i>
                                            @elseif($key === 'advanced_loan_management')
                                                <i class="fas fa-hand-holding-usd fa-lg"></i>
                                            @else
                                                <i class="fas fa-cog fa-lg"></i>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="card-title mb-1">{{ $module['name'] }}</h5>
                                        <p class="card-text text-muted mb-2">{{ $module['description'] }}</p>
                                        <div class="d-flex align-items-center">
                                            @if($key === 'qr_code' && isset($module['configured']))
                                                <span class="badge {{ $module['enabled'] ? ($module['configured'] ? 'bg-success' : 'bg-warning') : 'bg-secondary' }}">
                                                    @if($module['enabled'])
                                                        {{ $module['configured'] ? _lang('Active') : _lang('Needs Configuration') }}
                                                    @else
                                                        {{ _lang('Disabled') }}
                                                    @endif
                                                </span>
                                                @if($module['ethereum_enabled'])
                                                    <span class="badge bg-info ms-2">
                                                        <i class="fab fa-ethereum"></i> {{ _lang('Blockchain') }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="badge {{ $module['enabled'] ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $module['enabled'] ? _lang('Enabled') : _lang('Disabled') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" 
                                            class="btn {{ $module['enabled'] ? 'btn-danger' : 'btn-success' }} btn-sm toggle-module"
                                            data-module="{{ $key }}"
                                            data-enabled="{{ $module['enabled'] ? 'true' : 'false' }}">
                                        {{ $module['enabled'] ? _lang('Disable') : _lang('Enable') }}
                                    </button>
                                    @if($key === 'api' && $module['enabled'])
                                    <a href="{{ route('api.index') }}" class="btn btn-info btn-sm ms-2">
                                        <i class="fas fa-cog"></i> {{ _lang('Manage') }}
                                    </a>
                                    @elseif($key === 'qr_code' && $module['enabled'])
                                    <a href="{{ route('modules.qr_code.configure') }}" class="btn btn-info btn-sm ms-2">
                                        <i class="fas fa-cog"></i> {{ _lang('Configure') }}
                                    </a>
                                    <a href="{{ route('modules.qr_code.guide') }}" class="btn btn-outline-primary btn-sm ms-2">
                                        <i class="fas fa-book"></i> {{ _lang('Guide') }}
                                    </a>
                                    @elseif($key === 'advanced_loan_management' && $module['enabled'])
                                    <a href="{{ route('advanced_loan_management.index') }}" class="btn btn-info btn-sm ms-2">
                                        <i class="fas fa-cog"></i> {{ _lang('Manage') }}
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    $('.toggle-module').click(function() {
        var module = $(this).data('module');
        var enabled = $(this).data('enabled');
        var newStatus = !enabled;
        
        if (confirm(enabled ? '{{ _lang("Are you sure you want to disable this module?") }}' : '{{ _lang("Are you sure you want to enable this module?") }}')) {
            var url;
            if (module === 'vsla') {
                url = '{{ route("modules.toggle_vsla") }}';
            } else if (module === 'api') {
                url = '{{ route("modules.toggle_api") }}';
            } else if (module === 'qr_code') {
                url = '{{ route("modules.toggle_qr_code") }}';
            } else if (module === 'advanced_loan_management') {
                url = '{{ route("modules.toggle_advanced_loan_management") }}';
            }
            
            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    enabled: newStatus,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.result === 'success') {
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('{{ _lang("An error occurred while updating the module") }}');
                }
            });
        }
    });
});
</script>
@endsection
