@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Create Loan Terms and Privacy Policy') }}</span>
                <a href="{{ route('loan_terms.index') }}" class="btn btn-secondary btn-sm float-right">
                    <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('loan_terms.store') }}">
                    @csrf
                <div class="row">
                    <div class="col-lg-12">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Select Legal Template') }}</label>
                            <select class="form-control select2" id="legal_template_select" name="legal_template_id">
                                <option value="">{{ _lang('Choose a template to start with...') }}</option>
                                @foreach($legalTemplates as $template)
                                    <option value="{{ $template->id }}" 
                                            data-country="{{ $template->country_name }}"
                                            data-type="{{ $template->template_type }}"
                                            data-description="{{ $template->description }}">
                                        {{ $template->display_name }} - {{ $template->template_type }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">{{ _lang('Select a pre-built template based on your country and loan type, or create custom terms from scratch.') }}</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" value="{{ old('title') }}" required>
                        </div>
                    </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Loan Product') }}</label>
                                <select class="form-control select2" name="loan_product_id">
                                    <option value="">{{ _lang('General Terms (All Products)') }}</option>
                                    @foreach($loanProducts as $product)
                                        <option value="{{ $product->id }}" {{ old('loan_product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Version') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="version" value="{{ old('version', '1.0') }}" required>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Effective Date') }}</label>
                                <input type="date" class="form-control" name="effective_date" value="{{ old('effective_date') }}">
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Expiry Date') }}</label>
                                <input type="date" class="form-control" name="expiry_date" value="{{ old('expiry_date') }}">
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Terms and Conditions') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control summernote" name="terms_and_conditions" rows="10" required>{{ old('terms_and_conditions') }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Privacy Policy') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control summernote" name="privacy_policy" rows="10" required>{{ old('privacy_policy') }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1" {{ old('is_default') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_default">
                                        {{ _lang('Set as Default Terms') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Create Terms') }}
                                </button>
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

    // Handle legal template selection
    $('#legal_template_select').on('change', function() {
        var templateId = $(this).val();
        
        if (templateId) {
            // Show loading state
            $(this).prop('disabled', true);
            $('.form-group').find('.btn').prop('disabled', true);
            
            // Get template details via AJAX
            $.ajax({
                url: '{{ route("loan_terms.get_template_details") }}',
                method: 'GET',
                data: { template_id: templateId },
                success: function(response) {
                    if (response.success) {
                        var template = response.template;
                        
                        // Populate form fields
                        $('input[name="title"]').val(template.template_name + ' - ' + template.country_name);
                        $('.summernote[name="terms_and_conditions"]').summernote('code', template.terms_and_conditions);
                        $('.summernote[name="privacy_policy"]').summernote('code', template.privacy_policy);
                        
                        // Show template info
                        showTemplateInfo(template);
                    }
                },
                error: function() {
                    alert('Error loading template details. Please try again.');
                },
                complete: function() {
                    // Re-enable form
                    $('#legal_template_select').prop('disabled', false);
                    $('.form-group').find('.btn').prop('disabled', false);
                }
            });
        } else {
            // Clear template info
            hideTemplateInfo();
        }
    });
    
    function showTemplateInfo(template) {
        var infoHtml = `
            <div class="alert alert-info" id="template-info">
                <h6><i class="fas fa-info-circle"></i> Template Information</h6>
                <p><strong>Country:</strong> ${template.country_name}</p>
                <p><strong>Type:</strong> ${template.template_type}</p>
                <p><strong>Version:</strong> ${template.version}</p>
                <p><strong>Description:</strong> ${template.description}</p>
                <p><strong>Applicable Laws:</strong> ${template.applicable_laws.join(', ')}</p>
                <p><strong>Regulatory Bodies:</strong> ${template.regulatory_bodies.join(', ')}</p>
                <small class="text-muted">You can modify the terms and privacy policy below as needed.</small>
            </div>
        `;
        
        $('#template-info').remove();
        $('#legal_template_select').closest('.form-group').after(infoHtml);
    }
    
    function hideTemplateInfo() {
        $('#template-info').remove();
    }
});
</script>
@endsection
