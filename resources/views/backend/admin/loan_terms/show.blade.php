@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('View Loan Terms and Privacy Policy') }}</span>
                <div class="float-right">
                    <a href="{{ route('loan_terms.edit', $terms->id) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                    </a>
                    <a href="{{ route('loan_terms.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">{{ _lang('Title') }}</th>
                                <td>{{ $terms->title }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Loan Product') }}</th>
                                <td>
                                    @if($terms->loanProduct)
                                        <span class="badge badge-info">{{ $terms->loanProduct->name }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ _lang('General') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Version') }}</th>
                                <td>{{ $terms->formatted_version }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Status') }}</th>
                                <td>
                                    <span class="badge badge-{{ $terms->is_active ? 'success' : 'danger' }}">
                                        {{ $terms->status_label }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Default') }}</th>
                                <td>
                                    @if($terms->is_default)
                                        <span class="badge badge-primary">{{ _lang('Yes') }}</span>
                                    @else
                                        <span class="badge badge-light">{{ _lang('No') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-lg-6">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">{{ _lang('Effective Date') }}</th>
                                <td>{{ $terms->effective_date ? $terms->effective_date->format('M d, Y') : '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Expiry Date') }}</th>
                                <td>{{ $terms->expiry_date ? $terms->expiry_date->format('M d, Y') : '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Created By') }}</th>
                                <td>{{ $terms->creator->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Updated By') }}</th>
                                <td>{{ $terms->updater->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Created At') }}</th>
                                <td>{{ $terms->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Updated At') }}</th>
                                <td>{{ $terms->updated_at->format('M d, Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-lg-12">
                        <h5>{{ _lang('Terms and Conditions') }}</h5>
                        <div class="border p-3" style="max-height: 400px; overflow-y: auto;">
                            {!! $terms->terms_and_conditions !!}
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-lg-12">
                        <h5>{{ _lang('Privacy Policy') }}</h5>
                        <div class="border p-3" style="max-height: 400px; overflow-y: auto;">
                            {!! $terms->privacy_policy !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
