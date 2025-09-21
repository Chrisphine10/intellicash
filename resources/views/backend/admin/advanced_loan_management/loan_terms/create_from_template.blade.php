@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Create Loan Terms from Template') }}</span>
                <a href="{{ route('advanced_loan_management.index') }}" class="btn btn-secondary btn-sm float-right">
                    <i class="fas fa-arrow-left"></i> {{ _lang('Back to Dashboard') }}
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('loan_terms.store_from_template') }}">
                    @csrf
                    
                    <!-- Template Selection Section -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card bg-light">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-file-alt"></i> {{ _lang('Step 1: Select Legal Template') }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Choose a Legal Template') }} <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="legal_template_select" name="template_id" required>
                                            <option value="">{{ _lang('Choose a template to start with...') }}</option>
                                            @foreach($legalTemplates as $template)
                                                <option value="{{ $template->id }}" 
                                                        data-country="{{ $template->country_name }}"
                                                        data-type="{{ $template->template_type }}"
                                                        data-description="{{ $template->description }}"
                                                        {{ $selectedTemplate && $selectedTemplate->id == $template->id ? 'selected' : '' }}>
                                                    {{ $template->template_name }} ({{ $template->country_name }}) - {{ ucfirst($template->template_type) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('Select a pre-built template based on your country and loan type. The form will be prefilled with template data that you can edit.') }}</small>
                                    </div>
                                    
                                    <!-- Template Information Display -->
                                    <div id="template-info" class="alert alert-info" style="display: none;">
                                        <h6><i class="fas fa-info-circle"></i> {{ _lang('Template Information') }}</h6>
                                        <div id="template-details"></div>
                                        <small class="text-muted">{{ _lang('You can modify the terms and privacy policy below as needed.') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Fields Section -->
                    <div class="row mb-4">
                        <div class="col-lg-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-edit"></i> {{ _lang('Step 2: Customize Terms and Policy') }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="form-group">
                                                <label class="control-label">{{ _lang('Title') }} <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="title" value="{{ old('title', $selectedTemplate ? $selectedTemplate->template_name . ' - ' . $selectedTemplate->country_name : '') }}" required>
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
                                                <input type="text" class="form-control" name="version" value="{{ old('version', $selectedTemplate ? $selectedTemplate->version : '1.0') }}" required>
                                            </div>
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="form-group">
                                                <label class="control-label">{{ _lang('Effective Date') }}</label>
                                                <input type="date" class="form-control" name="effective_date" value="{{ old('effective_date', date('Y-m-d')) }}">
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
                                                <textarea class="form-control summernote" name="terms_and_conditions" rows="10" required>{{ old('terms_and_conditions', $selectedTemplate ? $selectedTemplate->terms_and_conditions : '') }}</textarea>
                                            </div>
                                        </div>

                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <label class="control-label">{{ _lang('Privacy Policy') }} <span class="text-danger">*</span></label>
                                                <textarea class="form-control summernote" name="privacy_policy" rows="10" required>{{ old('privacy_policy', $selectedTemplate ? $selectedTemplate->privacy_policy : '') }}</textarea>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Create Terms from Template') }}
                                </button>
                                <a href="{{ route('advanced_loan_management.index') }}" class="btn btn-secondary">
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

    // Handle legal template selection
    $('#legal_template_select').on('change', function() {
        var templateId = $(this).val();
        
        if (templateId) {
            // Show loading state
            $(this).prop('disabled', true);
            $('.form-group').find('button').prop('disabled', true);
            
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
                        $('input[name="version"]').val(template.version);
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
                    $('.form-group').find('button').prop('disabled', false);
                }
            });
        } else {
            // Clear template info
            hideTemplateInfo();
        }
    });
    
    function showTemplateInfo(template) {
        var infoHtml = `
            <p><strong>{{ _lang('Country') }}:</strong> ${template.country_name}</p>
            <p><strong>{{ _lang('Type') }}:</strong> ${template.template_type}</p>
            <p><strong>{{ _lang('Version') }}:</strong> ${template.version}</p>
            <p><strong>{{ _lang('Description') }}:</strong> ${template.description}</p>
            <p><strong>{{ _lang('Applicable Laws') }}:</strong> ${template.applicable_laws.join(', ')}</p>
            <p><strong>{{ _lang('Regulatory Bodies') }}:</strong> ${template.regulatory_bodies.join(', ')}</p>
        `;
        
        $('#template-details').html(infoHtml);
        $('#template-info').show();
    }
    
    function hideTemplateInfo() {
        $('#template-info').hide();
    }

    // Show template info if template is pre-selected
    @if($selectedTemplate)
        showTemplateInfo({
            country_name: '{{ $selectedTemplate->country_name }}',
            template_type: '{{ $selectedTemplate->template_type }}',
            version: '{{ $selectedTemplate->version }}',
            description: '{{ $selectedTemplate->description }}',
            applicable_laws: {!! json_encode($selectedTemplate->applicable_laws) !!},
            regulatory_bodies: {!! json_encode($selectedTemplate->regulatory_bodies) !!}
        });
    @endif
});
</script>
@endsection
