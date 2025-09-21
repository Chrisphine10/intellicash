@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ _lang('Audit Trail Details') }}</h4>
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-primary btn-sm float-right">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Audit Trail') }}
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">{{ _lang('Event Type') }}</th>
                                    <td>
                                        <span class="badge badge-{{ $audit->event_type == 'created' ? 'success' : ($audit->event_type == 'updated' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($audit->event_type) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('Model') }}</th>
                                    <td>{{ class_basename($audit->auditable_type) }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('Model ID') }}</th>
                                    <td>{{ $audit->auditable_id }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('User') }}</th>
                                    <td>
                                        @if($audit->user_name)
                                            {{ $audit->user_name }} ({{ ucfirst($audit->user_type) }})
                                        @else
                                            {{ _lang('System') }}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('IP Address') }}</th>
                                    <td>{{ $audit->ip_address ?? _lang('N/A') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('User Agent') }}</th>
                                    <td>{{ $audit->user_agent ?? _lang('N/A') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('URL') }}</th>
                                    <td>{{ $audit->url ?? _lang('N/A') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('Method') }}</th>
                                    <td>{{ $audit->method ?? _lang('N/A') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('Session ID') }}</th>
                                    <td>{{ $audit->session_id ?? _lang('N/A') }}</td>
                                </tr>
                                <tr>
                                    <th>{{ _lang('Created At') }}</th>
                                    <td>{{ $audit->created_at->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            @if($audit->description)
                            <h5>{{ _lang('Description') }}</h5>
                            <p class="text-muted">{{ $audit->description }}</p>
                            @endif

                            @if($audit->old_values && is_array($audit->old_values) && count($audit->old_values) > 0)
                            <h5>{{ _lang('Old Values') }}</h5>
                            <pre class="bg-light p-3 rounded"><code>{{ json_encode($audit->old_values, JSON_PRETTY_PRINT) }}</code></pre>
                            @endif

                            @if($audit->new_values && is_array($audit->new_values) && count($audit->new_values) > 0)
                            <h5>{{ _lang('New Values') }}</h5>
                            <pre class="bg-light p-3 rounded"><code>{{ json_encode($audit->new_values, JSON_PRETTY_PRINT) }}</code></pre>
                            @endif

                            @if($audit->metadata && is_array($audit->metadata) && count($audit->metadata) > 0)
                            <h5>{{ _lang('Metadata') }}</h5>
                            <pre class="bg-light p-3 rounded"><code>{{ json_encode($audit->metadata, JSON_PRETTY_PRINT) }}</code></pre>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
