@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">{{ _lang('Asset Categories') }}</h4>
                <a href="{{ route('asset-categories.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> {{ _lang('Add Category') }}
                </a>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ _lang('Name') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Description') }}</th>
                                <th>{{ _lang('Assets Count') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categories as $category)
                            <tr>
                                <td>
                                    <strong>{{ $category->name }}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">{{ ucfirst($category->type) }}</span>
                                </td>
                                <td>{{ $category->description }}</td>
                                <td>
                                    <span class="badge badge-primary">{{ $category->assets_count }}</span>
                                </td>
                                <td>
                                    @if($category->is_active)
                                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                                    @else
                                        <span class="badge badge-secondary">{{ _lang('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            {{ _lang('Actions') }}
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('asset-categories.show', $category) }}">
                                                <i class="fas fa-eye"></i> {{ _lang('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('asset-categories.edit', $category) }}">
                                                <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('asset-categories.toggle-status', $category) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="fas fa-toggle-{{ $category->is_active ? 'off' : 'on' }}"></i> 
                                                    {{ $category->is_active ? _lang('Deactivate') : _lang('Activate') }}
                                                </button>
                                            </form>
                                            <form action="{{ route('asset-categories.destroy', $category) }}" method="POST" class="d-inline delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="fas fa-trash"></i> {{ _lang('Delete') }}
                                                </button>
                                            </form>
                                        </div>
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
    $('.delete-form').on('submit', function(e) {
        e.preventDefault();
        if (confirm('{{ _lang("Are you sure you want to delete this category?") }}')) {
            this.submit();
        }
    });
});
</script>
@endsection
