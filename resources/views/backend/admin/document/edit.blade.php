@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Edit Document') }}</span>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('documents.update', $document->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="{{ old('title', $document->title) }}" required>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Description') }}</label>
                                <textarea class="form-control" name="description" rows="3">{{ old('description', $document->description) }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Category') }} <span class="text-danger">*</span></label>
                                <select class="form-control" name="category" required>
                                    <option value="">{{ _lang('Select Category') }}</option>
                                    <option value="terms_and_conditions" {{ old('category', $document->category) == 'terms_and_conditions' ? 'selected' : '' }}>{{ _lang('Terms and Conditions') }}</option>
                                    <option value="privacy_policy" {{ old('category', $document->category) == 'privacy_policy' ? 'selected' : '' }}>{{ _lang('Privacy Policy') }}</option>
                                    <option value="loan_agreement" {{ old('category', $document->category) == 'loan_agreement' ? 'selected' : '' }}>{{ _lang('Loan Agreement') }}</option>
                                    <option value="legal_document" {{ old('category', $document->category) == 'legal_document' ? 'selected' : '' }}>{{ _lang('Legal Document') }}</option>
                                    <option value="policy" {{ old('category', $document->category) == 'policy' ? 'selected' : '' }}>{{ _lang('Policy') }}</option>
                                    <option value="other" {{ old('category', $document->category) == 'other' ? 'selected' : '' }}>{{ _lang('Other') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Version') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="version" value="{{ old('version', $document->version) }}" required>
                                <small class="form-text text-muted">{{ _lang('e.g., 1.0, 2.1, etc.') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Current File') }}</label>
                                <div class="alert alert-info">
                                    <i class="fas fa-file-pdf mr-1"></i>
                                    <strong>{{ $document->file_name }}</strong> ({{ $document->formatted_file_size }})
                                    <a href="{{ route('documents.download', $document->id) }}" class="btn btn-sm btn-outline-primary ml-2">
                                        <i class="fas fa-download mr-1"></i>{{ _lang('Download') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Replace File') }}</label>
                                <input type="file" class="form-control" name="file" accept=".pdf">
                                <small class="form-text text-muted">{{ _lang('Leave empty to keep current file. Only PDF files are allowed. Maximum size: 10MB') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Tags') }}</label>
                                <input type="text" class="form-control" name="tags" value="{{ old('tags', $document->tags ? implode(', ', $document->tags) : '') }}" placeholder="{{ _lang('Enter tags separated by commas') }}">
                                <small class="form-text text-muted">{{ _lang('e.g., loan, terms, legal, policy') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $document->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label">
                                        {{ _lang('Active') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_public" value="1" {{ old('is_public', $document->is_public) ? 'checked' : '' }}>
                                    <label class="form-check-label">
                                        {{ _lang('Make this document public') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>{{ _lang('Update Document') }}
                                </button>
                                <a href="{{ route('documents.show', $document->id) }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-1"></i>{{ _lang('Back') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Document History -->
@if($document->created_at != $document->updated_at)
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Document History') }}</span>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">{{ _lang('Document Created') }}</h6>
                            <p class="timeline-text">{{ _lang('Created by') }}: {{ $document->creator->name ?? _lang('Unknown') }}</p>
                            <small class="text-muted">{{ $document->created_at->format('M d, Y H:i') }}</small>
                        </div>
                    </div>
                    @if($document->updated_by)
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="timeline-title">{{ _lang('Document Updated') }}</h6>
                            <p class="timeline-text">{{ _lang('Updated by') }}: {{ $document->updater->name ?? _lang('Unknown') }}</p>
                            <small class="text-muted">{{ $document->updated_at->format('M d, Y H:i') }}</small>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
<script>
$(document).ready(function() {
    // File size validation
    $('input[type="file"]').change(function() {
        const file = this.files[0];
        if (file) {
            const fileSize = file.size / 1024 / 1024; // Convert to MB
            if (fileSize > 10) {
                alert('{{ _lang("File size must be less than 10MB") }}');
                this.value = '';
            }
        }
    });
});
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #dee2e6;
}

.timeline-title {
    margin: 0 0 5px 0;
    font-weight: 600;
}

.timeline-text {
    margin: 0 0 5px 0;
    color: #6c757d;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -25px;
    top: 12px;
    width: 2px;
    height: calc(100% + 8px);
    background: #dee2e6;
}
</style>
@endsection
