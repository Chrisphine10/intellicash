@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <div class="panel-title">{{ _lang('Document Details') }}</div>
                <div>
                    <a class="btn btn-primary btn-xs" href="{{ route('documents.download', $document->id) }}">
                        <i class="fas fa-download mr-1"></i>{{ _lang('Download') }}
                    </a>
                    <a class="btn btn-info btn-xs" href="{{ route('documents.view', $document->id) }}" target="_blank">
                        <i class="fas fa-eye mr-1"></i>{{ _lang('View PDF') }}
                    </a>
                    <a class="btn btn-warning btn-xs" href="{{ route('documents.edit', $document->id) }}">
                        <i class="fas fa-edit mr-1"></i>{{ _lang('Edit') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <td width="30%">{{ _lang('Title') }}</td>
                        <td>{{ $document->title }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Description') }}</td>
                        <td>{{ $document->description ?: _lang('No description provided') }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Category') }}</td>
                        <td>
                            <span class="badge badge-primary">{{ $document->category_label }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ _lang('File Name') }}</td>
                        <td>{{ $document->file_name }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('File Size') }}</td>
                        <td>{{ $document->formatted_file_size }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('File Type') }}</td>
                        <td>{{ $document->file_type }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Version') }}</td>
                        <td>{{ $document->version }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Status') }}</td>
                        <td>
                            @if($document->is_active)
                                <span class="badge badge-success">{{ _lang('Active') }}</span>
                            @else
                                <span class="badge badge-danger">{{ _lang('Inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Visibility') }}</td>
                        <td>
                            @if($document->is_public)
                                <span class="badge badge-info">{{ _lang('Public') }}</span>
                            @else
                                <span class="badge badge-secondary">{{ _lang('Private') }}</span>
                            @endif
                        </td>
                    </tr>
                    @if($document->tags && count($document->tags) > 0)
                    <tr>
                        <td>{{ _lang('Tags') }}</td>
                        <td>
                            @foreach($document->tags as $tag)
                                <span class="badge badge-light mr-1">{{ $tag }}</span>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td>{{ _lang('Created By') }}</td>
                        <td>{{ $document->creator->name ?? _lang('Unknown') }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Created At') }}</td>
                        <td>{{ $document->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @if($document->updated_by)
                    <tr>
                        <td>{{ _lang('Last Updated By') }}</td>
                        <td>{{ $document->updater->name ?? _lang('Unknown') }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Updated At') }}</td>
                        <td>{{ $document->updated_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Document Actions') }}</span>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('documents.download', $document->id) }}" class="btn btn-primary">
                        <i class="fas fa-download mr-1"></i>{{ _lang('Download PDF') }}
                    </a>
                    <a href="{{ route('documents.view', $document->id) }}" target="_blank" class="btn btn-info">
                        <i class="fas fa-eye mr-1"></i>{{ _lang('View in Browser') }}
                    </a>
                    <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-warning">
                        <i class="fas fa-edit mr-1"></i>{{ _lang('Edit Document') }}
                    </a>
                    <button class="btn btn-danger" onclick="deleteDocument({{ $document->id }})">
                        <i class="fas fa-trash mr-1"></i>{{ _lang('Delete Document') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Document Statistics') }}</span>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary">{{ $document->formatted_file_size }}</h4>
                        <small class="text-muted">{{ _lang('File Size') }}</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-info">{{ $document->version }}</h4>
                        <small class="text-muted">{{ _lang('Version') }}</small>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-success">{{ $document->created_at->diffForHumans() }}</h4>
                        <small class="text-muted">{{ _lang('Created') }}</small>
                    </div>
                    <div class="col-6">
                        <h4 class="text-warning">
                            @if($document->updated_at != $document->created_at)
                                {{ $document->updated_at->diffForHumans() }}
                            @else
                                {{ _lang('Never') }}
                            @endif
                        </h4>
                        <small class="text-muted">{{ _lang('Updated') }}</small>
                    </div>
                </div>
            </div>
        </div>

        @if($document->category == 'terms_and_conditions' || $document->category == 'privacy_policy')
        <div class="card mt-3">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Public Access') }}</span>
            </div>
            <div class="card-body">
                @if($document->is_public)
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ _lang('This document is publicly accessible') }}
                    </div>
                    <p class="mb-2">{{ _lang('Public URL') }}:</p>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" value="{{ route('documents.public.view', $document->id) }}" readonly>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyToClipboard(this)">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="fas fa-lock mr-1"></i>
                        {{ _lang('This document is private and requires authentication') }}
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@section('script')
<script>
function deleteDocument(id) {
    Swal.fire({
        title: '{{ _lang("Are you sure?") }}',
        text: '{{ _lang("You will not be able to recover this document!") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '{{ _lang("Yes, delete it!") }}',
        cancelButtonText: '{{ _lang("Cancel") }}'
    }).then((result) => {
        if (result.value) {
            $.ajax({
                url: '/documents/' + id,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('{{ _lang("Deleted!") }}', response.message, 'success').then(() => {
                            window.location.href = '{{ route("documents.index") }}';
                        });
                    } else {
                        Swal.fire('{{ _lang("Error!") }}', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('{{ _lang("Error!") }}', '{{ _lang("Something went wrong!") }}', 'error');
                }
            });
        }
    });
}

function copyToClipboard(button) {
    const input = button.previousElementSibling;
    input.select();
    document.execCommand('copy');
    
    // Change button text temporarily
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i>';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
</script>
@endsection
