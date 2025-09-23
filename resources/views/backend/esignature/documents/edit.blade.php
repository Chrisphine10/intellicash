@extends('layouts.app')

@section('title', 'Edit E-Signature Document')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Edit E-Signature Document</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('esignature.esignature-documents.update', $document->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Document Title *</label>
                                <input type="text" class="form-control" name="title" value="{{ old('title', $document->title) }}" required>
                                @error('title')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Document Type *</label>
                                <select class="form-control" name="document_type" required>
                                    <option value="">Select Type</option>
                                    <option value="contract" {{ old('document_type', $document->document_type) == 'contract' ? 'selected' : '' }}>Contract</option>
                                    <option value="agreement" {{ old('document_type', $document->document_type) == 'agreement' ? 'selected' : '' }}>Agreement</option>
                                    <option value="form" {{ old('document_type', $document->document_type) == 'form' ? 'selected' : '' }}>Form</option>
                                    <option value="policy" {{ old('document_type', $document->document_type) == 'policy' ? 'selected' : '' }}>Policy</option>
                                    <option value="other" {{ old('document_type', $document->document_type) == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('document_type')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3">{{ old('description', $document->description) }}</textarea>
                        @error('description')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sender Name</label>
                                <input type="text" class="form-control" name="sender_name" value="{{ old('sender_name', $document->sender_name) }}">
                                @error('sender_name')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sender Email</label>
                                <input type="email" class="form-control" name="sender_email" value="{{ old('sender_email', $document->sender_email) }}">
                                @error('sender_email')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sender Company</label>
                                <input type="text" class="form-control" name="sender_company" value="{{ old('sender_company', $document->sender_company) }}">
                                @error('sender_company')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Custom Message</label>
                        <textarea class="form-control" name="custom_message" rows="3" placeholder="Optional message to include in the signing email">{{ old('custom_message', $document->custom_message) }}</textarea>
                        @error('custom_message')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiration Date</label>
                        <input type="datetime-local" class="form-control" name="expires_at" 
                               value="{{ old('expires_at', $document->expires_at ? $document->expires_at->format('Y-m-d\TH:i') : '') }}">
                        <small class="text-muted">Leave empty for no expiration</small>
                        @error('expires_at')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group text-right">
                        <a href="{{ route('esignature.esignature-documents.show', $document->id) }}" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Back to Document
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Update Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
