@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Edit Loan Terms and Privacy Policy') }}</span>
                <a href="{{ route('loan_terms.index') }}" class="btn btn-secondary btn-sm float-right">
                    <i class="fas fa-arrow-left"></i> {{ _lang('Back') }}
                </a>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('loan_terms.update', $terms->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="{{ old('title', $terms->title) }}" required>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Loan Product') }}</label>
                                <select class="form-control select2" name="loan_product_id">
                                    <option value="">{{ _lang('General Terms (All Products)') }}</option>
                                    @foreach($loanProducts as $product)
                                        <option value="{{ $product->id }}" {{ old('loan_product_id', $terms->loan_product_id) == $product->id ? 'selected' : '' }}>
                                            {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Version') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="version" value="{{ old('version', $terms->version) }}" required>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Effective Date') }}</label>
                                <input type="date" class="form-control" name="effective_date" value="{{ old('effective_date', $terms->effective_date ? $terms->effective_date->format('Y-m-d') : '') }}">
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Expiry Date') }}</label>
                                <input type="date" class="form-control" name="expiry_date" value="{{ old('expiry_date', $terms->expiry_date ? $terms->expiry_date->format('Y-m-d') : '') }}">
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $terms->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        {{ _lang('Active') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Terms and Conditions') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control summernote" name="terms_and_conditions" rows="10" required>{{ old('terms_and_conditions', $terms->terms_and_conditions) }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Privacy Policy') }} <span class="text-danger">*</span></label>
                                <textarea class="form-control summernote" name="privacy_policy" rows="10" required>{{ old('privacy_policy', $terms->privacy_policy) }}</textarea>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1" {{ old('is_default', $terms->is_default) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_default">
                                        {{ _lang('Set as Default Terms') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Update Terms') }}
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
});
</script>
@endsection
