@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Edit Legal Template') }} - {{ $template->template_name }}</span>
                <a href="{{ route('legal_templates.index') }}" class="btn btn-secondary btn-sm float-right">
                    <i class="fas fa-arrow-left"></i> {{ _lang('Back to Templates') }}
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('legal_templates.update', $template->id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Template Name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="template_name" value="{{ old('template_name', $template->template_name) }}" required>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Version') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="version" value="{{ old('version', $template->version) }}" required>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Description') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="description" rows="3" required>{{ old('description', $template->description) }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Applicable Laws') }}</label>
                                <div id="applicable_laws_container">
                                    @if($template->applicable_laws)
                                        @foreach($template->applicable_laws as $index => $law)
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" name="applicable_laws[]" value="{{ $law }}">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger remove-law-btn">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-success" id="add_law_btn">
                                    <i class="fas fa-plus"></i> {{ _lang('Add Law') }}
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Regulatory Bodies') }}</label>
                                <div id="regulatory_bodies_container">
                                    @if($template->regulatory_bodies)
                                        @foreach($template->regulatory_bodies as $index => $body)
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" name="regulatory_bodies[]" value="{{ $body }}">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger remove-body-btn">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-success" id="add_body_btn">
                                    <i class="fas fa-plus"></i> {{ _lang('Add Regulatory Body') }}
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Terms and Conditions') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control summernote" name="terms_and_conditions" rows="15" required>{{ old('terms_and_conditions', $template->terms_and_conditions) }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Privacy Policy') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control summernote" name="privacy_policy" rows="15" required>{{ old('privacy_policy', $template->privacy_policy) }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Update Template') }}
                                </button>
                                <a href="{{ route('legal_templates.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Initialize Summernote
    $('.summernote').summernote({
        height: 300,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });

    // Add new law
    $('#add_law_btn').click(function() {
        var lawHtml = `
            <div class="input-group mb-2">
                <input type="text" class="form-control" name="applicable_laws[]" placeholder="{{ _lang('Enter applicable law') }}">
                <div class="input-group-append">
                    <button type="button" class="btn btn-danger remove-law-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        $('#applicable_laws_container').append(lawHtml);
    });

    // Remove law
    $(document).on('click', '.remove-law-btn', function() {
        $(this).closest('.input-group').remove();
    });

    // Add new regulatory body
    $('#add_body_btn').click(function() {
        var bodyHtml = `
            <div class="input-group mb-2">
                <input type="text" class="form-control" name="regulatory_bodies[]" placeholder="{{ _lang('Enter regulatory body') }}">
                <div class="input-group-append">
                    <button type="button" class="btn btn-danger remove-body-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        $('#regulatory_bodies_container').append(bodyHtml);
    });

    // Remove regulatory body
    $(document).on('click', '.remove-body-btn', function() {
        $(this).closest('.input-group').remove();
    });
});
</script>
@endsection
