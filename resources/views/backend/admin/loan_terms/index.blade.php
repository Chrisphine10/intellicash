@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Loan Terms and Privacy Policy Management') }}</span>
                <a href="{{ route('loan_terms.create') }}" class="btn btn-primary btn-sm float-right">
                    <i class="fas fa-plus"></i> {{ _lang('Add New Terms') }}
                </a>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered" id="terms_table">
                        <thead>
                            <tr>
                                <th>{{ _lang('Title') }}</th>
                                <th>{{ _lang('Loan Product') }}</th>
                                <th>{{ _lang('Version') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Default') }}</th>
                                <th>{{ _lang('Effective Date') }}</th>
                                <th>{{ _lang('Created By') }}</th>
                                <th>{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($terms as $term)
                            <tr>
                                <td>{{ $term->title }}</td>
                                <td>
                                    @if($term->loanProduct)
                                        <span class="badge badge-info">{{ $term->loanProduct->name }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ _lang('General') }}</span>
                                    @endif
                                </td>
                                <td>{{ $term->formatted_version }}</td>
                                <td>
                                    <span class="badge badge-{{ $term->is_active ? 'success' : 'danger' }}">
                                        {{ $term->status_label }}
                                    </span>
                                </td>
                                <td>
                                    @if($term->is_default)
                                        <span class="badge badge-primary">{{ _lang('Yes') }}</span>
                                    @else
                                        <span class="badge badge-light">{{ _lang('No') }}</span>
                                    @endif
                                </td>
                                <td>{{ $term->effective_date ? $term->effective_date->format('M d, Y') : '-' }}</td>
                                <td>{{ $term->creator->name ?? '-' }}</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('loan_terms.show', $term->id) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('loan_terms.edit', $term->id) }}" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if(!$term->is_default)
                                            <form method="POST" action="{{ route('loan_terms.set_default', $term->id) }}" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('loan_terms.toggle_active', $term->id) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-{{ $term->is_active ? 'warning' : 'success' }} btn-sm" 
                                                    title="{{ $term->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="fas fa-{{ $term->is_active ? 'pause' : 'play' }}"></i>
                                            </button>
                                        </form>
                                        @if(!$term->is_default)
                                            <form method="POST" action="{{ route('loan_terms.destroy', $term->id) }}" 
                                                  style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this terms?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    $('#terms_table').DataTable({
        responsive: true,
        autoWidth: false,
        ordering: false,
        language: {
            emptyTable: "{{ _lang('No terms found') }}",
            info: "{{ _lang('Showing') }} _START_ {{ _lang('to') }} _END_ {{ _lang('of') }} _TOTAL_ {{ _lang('entries') }}",
            infoEmpty: "{{ _lang('Showing') }} 0 {{ _lang('to') }} 0 {{ _lang('of') }} 0 {{ _lang('entries') }}",
            lengthMenu: "{{ _lang('Show') }} _MENU_ {{ _lang('entries') }}",
            search: "{{ _lang('Search') }}:",
            paginate: {
                first: "{{ _lang('First') }}",
                last: "{{ _lang('Last') }}",
                next: "{{ _lang('Next') }}",
                previous: "{{ _lang('Previous') }}"
            }
        }
    });
});
</script>
@endsection
