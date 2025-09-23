@extends('layouts.app')

@section('title', 'Create E-Signature Document')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Create E-Signature Document</h4>
            </div>
            <div class="card-body">
                <form action="{{ route('esignature.esignature-documents.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Document Title</label>
                                <input type="text" class="form-control" name="title" value="{{ old('title') }}" required>
                                @error('title')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Document Type</label>
                                <select class="form-control" name="document_type" required>
                                    <option value="">Select Type</option>
                                    <option value="contract" {{ old('document_type') == 'contract' ? 'selected' : '' }}>Contract</option>
                                    <option value="agreement" {{ old('document_type') == 'agreement' ? 'selected' : '' }}>Agreement</option>
                                    <option value="form" {{ old('document_type') == 'form' ? 'selected' : '' }}>Form</option>
                                    <option value="policy" {{ old('document_type') == 'policy' ? 'selected' : '' }}>Policy</option>
                                    <option value="other" {{ old('document_type') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('document_type')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Document File</label>
                        <input type="file" class="form-control" name="document_file" accept=".pdf,.doc,.docx" required>
                        <small class="text-muted">Supported formats: PDF, DOC, DOCX (Max: 10MB)</small>
                        @error('document_file')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sender Name</label>
                                <input type="text" class="form-control" name="sender_name" value="{{ old('sender_name', auth()->user()->name) }}">
                                @error('sender_name')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sender Email</label>
                                <input type="email" class="form-control" name="sender_email" value="{{ old('sender_email', auth()->user()->email) }}">
                                @error('sender_email')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Sender Company</label>
                                <input type="text" class="form-control" name="sender_company" value="{{ old('sender_company') }}">
                                @error('sender_company')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Custom Message</label>
                        <textarea class="form-control" name="custom_message" rows="3" placeholder="Optional message to include in the signing email">{{ old('custom_message') }}</textarea>
                        @error('custom_message')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Expiration Date</label>
                        <input type="datetime-local" class="form-control" name="expires_at" value="{{ old('expires_at') }}">
                        <small class="text-muted">Leave empty for no expiration</small>
                        @error('expires_at')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Signers Section -->
                    <div class="form-group">
                        <label class="form-label">Signers</label>
                        <div id="signers-container">
                            <div class="signer-row row mb-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="signers[0][name]" placeholder="Full Name" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="email" class="form-control" name="signers[0][email]" placeholder="Email Address" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="signers[0][phone]" placeholder="Phone (Optional)">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSigner(this)">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addSigner()">
                            <i class="fa fa-plus"></i> Add Signer
                        </button>
                        @error('signers')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group text-right">
                        <a href="{{ route('esignature.esignature-documents.index') }}" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Create Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let signerIndex = 1;

function addSigner() {
    const container = document.getElementById('signers-container');
    const newRow = document.createElement('div');
    newRow.className = 'signer-row row mb-3';
    newRow.innerHTML = `
        <div class="col-md-4">
            <input type="text" class="form-control" name="signers[${signerIndex}][name]" placeholder="Full Name" required>
        </div>
        <div class="col-md-4">
            <input type="email" class="form-control" name="signers[${signerIndex}][email]" placeholder="Email Address" required>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control" name="signers[${signerIndex}][phone]" placeholder="Phone (Optional)">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm" onclick="removeSigner(this)">
                <i class="fa fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
    signerIndex++;
}

function removeSigner(button) {
    const container = document.getElementById('signers-container');
    if (container.children.length > 1) {
        button.closest('.signer-row').remove();
    } else {
        alert('At least one signer is required');
    }
}

// Set default expiration date to 30 days from now
document.addEventListener('DOMContentLoaded', function() {
    const expiresAtInput = document.querySelector('input[name="expires_at"]');
    if (!expiresAtInput.value) {
        const now = new Date();
        now.setDate(now.getDate() + 30);
        const formattedDate = now.toISOString().slice(0, 16);
        expiresAtInput.value = formattedDate;
    }
});
</script>
@endpush
