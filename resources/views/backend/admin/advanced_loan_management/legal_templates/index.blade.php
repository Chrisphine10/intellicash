@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Legal Templates Management') }}</span>
                <a href="{{ route('advanced_loan_management.index') }}" class="btn btn-secondary btn-sm float-right">
                    <i class="fas fa-arrow-left"></i> {{ _lang('Back to Dashboard') }}
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>{{ _lang('Country') }}</th>
                                <th>{{ _lang('Template Name') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Version') }}</th>
                                <th>{{ _lang('Description') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>
                                        <span class="badge badge-primary">{{ $template->country_name }}</span>
                                    </td>
                                    <td>{{ $template->template_name }}</td>
                                    <td>{{ ucfirst($template->template_type) }}</td>
                                    <td>{{ $template->formatted_version }}</td>
                                    <td>{{ Str::limit($template->description, 50) }}</td>
                                    <td>
                                        <a href="{{ route('legal_templates.edit', $template->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">{{ _lang('No templates found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
