@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Upload Document') }}</span>
                <div class="card-tools">
                    <a href="{{ route('documents.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Documents') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="{{ old('title') }}" required>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Description') }}</label>
                                <textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Category') }} <span class="text-danger">*</span></label>
                                <select class="form-control" name="category" required>
                                    <option value="">{{ _lang('Select Category') }}</option>
                                    <option value="terms_and_conditions" {{ old('category') == 'terms_and_conditions' ? 'selected' : '' }}>{{ _lang('Terms and Conditions') }}</option>
                                    <option value="privacy_policy" {{ old('category') == 'privacy_policy' ? 'selected' : '' }}>{{ _lang('Privacy Policy') }}</option>
                                    <option value="loan_agreement" {{ old('category') == 'loan_agreement' ? 'selected' : '' }}>{{ _lang('Loan Agreement') }}</option>
                                    <option value="legal_document" {{ old('category') == 'legal_document' ? 'selected' : '' }}>{{ _lang('Legal Document') }}</option>
                                    <option value="policy" {{ old('category') == 'policy' ? 'selected' : '' }}>{{ _lang('Policy') }}</option>
                                    <option value="other" {{ old('category') == 'other' ? 'selected' : '' }}>{{ _lang('Other') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Version') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="version" value="{{ old('version', '1.0') }}" required>
                                <small class="form-text text-muted">{{ _lang('e.g., 1.0, 2.1, etc.') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('PDF File') }} <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="file" accept=".pdf" required>
                                <small class="form-text text-muted">{{ _lang('Only PDF files are allowed. Maximum size: 10MB') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Tags') }}</label>
                                <input type="text" class="form-control" name="tags" value="{{ old('tags') }}" placeholder="{{ _lang('Enter tags separated by commas') }}">
                                <small class="form-text text-muted">{{ _lang('e.g., loan, terms, legal, policy') }}</small>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_public" value="1" {{ old('is_public') ? 'checked' : '' }}>
                                    <label class="form-check-label">
                                        {{ _lang('Make this document public') }}
                                    </label>
                                    <small class="form-text text-muted">{{ _lang('Public documents can be accessed by members without login') }}</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload mr-1"></i>{{ _lang('Upload Document') }}
                                </button>
                                <a href="{{ route('documents.index') }}" class="btn btn-secondary">
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

<!-- Upload Guidelines -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Upload Guidelines') }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6>{{ _lang('File Requirements') }}</h6>
                        <ul>
                            <li>{{ _lang('Only PDF files are accepted') }}</li>
                            <li>{{ _lang('Maximum file size: 10MB') }}</li>
                            <li>{{ _lang('File name should be descriptive') }}</li>
                            <li>{{ _lang('Ensure PDF is not password protected') }}</li>
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <h6>{{ _lang('Category Guidelines') }}</h6>
                        <ul>
                            <li><strong>{{ _lang('Terms and Conditions') }}:</strong> {{ _lang('General terms for loans and services') }}</li>
                            <li><strong>{{ _lang('Privacy Policy') }}:</strong> {{ _lang('Data protection and privacy policies') }}</li>
                            <li><strong>{{ _lang('Loan Agreement') }}:</strong> {{ _lang('Specific loan contract templates') }}</li>
                            <li><strong>{{ _lang('Legal Document') }}:</strong> {{ _lang('Other legal documents and forms') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
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

    // Form validation
    $('form').submit(function(e) {
        const fileInput = $('input[type="file"]')[0];
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('{{ _lang("Please select a PDF file") }}');
            return false;
        }
    });
});
</script>
@endsection
