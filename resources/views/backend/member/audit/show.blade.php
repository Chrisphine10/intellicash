@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Activity Details') }}</span>
                <div class="float-right">
                    <a href="{{ route('member.audit.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>{{ _lang('Activity Information') }}</h5>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>{{ _lang('Activity Type') }}</strong></td>
                                <td>
                                    <span class="badge badge-{{ $audit->event_type == 'created' ? 'success' : ($audit->event_type == 'updated' ? 'warning' : ($audit->event_type == 'deleted' ? 'danger' : 'info')) }}">
                                        {{ ucfirst(str_replace('_', ' ', $audit->event_type)) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Date/Time') }}</strong></td>
                                <td>{{ $audit->created_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Description') }}</strong></td>
                                <td>{{ $audit->description ?: 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Model Type') }}</strong></td>
                                <td>{{ class_basename($audit->auditable_type) }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Model ID') }}</strong></td>
                                <td>{{ $audit->auditable_id }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>{{ _lang('Technical Information') }}</h5>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>{{ _lang('IP Address') }}</strong></td>
                                <td>{{ $audit->ip_address ?: 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('User Agent') }}</strong></td>
                                <td>{{ $audit->user_agent ?: 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Session ID') }}</strong></td>
                                <td>{{ $audit->session_id ?: 'N/A' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($audit->url)
                <div class="row mt-3">
                    <div class="col-12">
                        <h5>{{ _lang('Request Information') }}</h5>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>{{ _lang('URL') }}</strong></td>
                                <td>{{ $audit->url }}</td>
                            </tr>
                            <tr>
                                <td><strong>{{ _lang('Method') }}</strong></td>
                                <td><span class="badge badge-info">{{ $audit->method }}</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
                @endif

                @if($audit->changes_summary && count($audit->changes_summary) > 0)
                <div class="row mt-3">
                    <div class="col-12">
                        <h5>{{ _lang('Changes Made') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Field') }}</th>
                                        <th>{{ _lang('Old Value') }}</th>
                                        <th>{{ _lang('New Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($audit->changes_summary as $field => $change)
                                    <tr>
                                        <td><strong>{{ ucfirst(str_replace('_', ' ', $field)) }}</strong></td>
                                        <td>{{ $change['old'] }}</td>
                                        <td>{{ $change['new'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                @if($audit->metadata && count($audit->metadata) > 0)
                <div class="row mt-3">
                    <div class="col-12">
                        <h5>{{ _lang('Additional Information') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Key') }}</th>
                                        <th>{{ _lang('Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($audit->metadata as $key => $value)
                                    <tr>
                                        <td><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}</strong></td>
                                        <td>{{ $value }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
