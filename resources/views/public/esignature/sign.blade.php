<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Document - {{ $document->title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Signature Pad -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    
    <style>
        .signature-canvas {
            border: 2px dashed #ccc;
            border-radius: 8px;
            cursor: crosshair;
        }
        .signature-canvas:hover {
            border-color: #007bff;
        }
        .field-container {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .document-preview {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .signature-toolbar {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .btn-signature-type {
            margin-right: 10px;
        }
        .signature-preview {
            border: 1px solid #ddd;
            border-radius: 4px;
            max-width: 200px;
            max-height: 100px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Document Preview -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>{{ $document->title }}</h4>
                        <p class="mb-0 text-muted">{{ $document->description }}</p>
                    </div>
                    <div class="card-body">
                        <div class="document-preview">
                            <iframe src="{{ route('esignature.public.download-document', $signature->signature_token) }}" 
                                    width="100%" height="600" style="border: none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Signing Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-signature"></i> Sign Document</h5>
                        <p class="mb-0">Signing as: <strong>{{ $signature->signer_name }}</strong></p>
                        <small class="text-muted">{{ $signature->signer_email }}</small>
                    </div>
                    <div class="card-body">
                        <!-- Document Info -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Document Information</h6>
                            <ul class="mb-0">
                                <li><strong>Type:</strong> {{ ucfirst($document->document_type) }}</li>
                                <li><strong>Created:</strong> {{ $document->created_at->format('M d, Y') }}</li>
                                @if($document->expires_at)
                                    <li><strong>Expires:</strong> {{ $document->expires_at->format('M d, Y H:i') }}</li>
                                @endif
                                <li><strong>Signers:</strong> {{ $document->getSignerCount() }}</li>
                            </ul>
                        </div>

                        <!-- Custom Message -->
                        @if($document->custom_message)
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-envelope"></i> Message from {{ $document->sender_name }}</h6>
                                <p class="mb-0">{{ $document->custom_message }}</p>
                            </div>
                        @endif

                        <!-- Signature Type Selection -->
                        <div class="signature-toolbar">
                            <h6>Choose Signature Type:</h6>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-primary btn-signature-type active" 
                                        data-type="drawn" id="btn-drawn">
                                    <i class="fas fa-pen"></i> Draw
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-signature-type" 
                                        data-type="typed" id="btn-typed">
                                    <i class="fas fa-keyboard"></i> Type
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-signature-type" 
                                        data-type="uploaded" id="btn-uploaded">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                            </div>
                        </div>

                        <!-- Signature Canvas -->
                        <div id="signature-drawn" class="signature-section">
                            <h6>Draw Your Signature:</h6>
                            <canvas id="signatureCanvas" class="signature-canvas w-100" width="400" height="150"></canvas>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-secondary" id="clearSignature">
                                    <i class="fas fa-eraser"></i> Clear
                                </button>
                            </div>
                        </div>

                        <!-- Typed Signature -->
                        <div id="signature-typed" class="signature-section" style="display: none;">
                            <h6>Type Your Signature:</h6>
                            <input type="text" class="form-control" id="typedSignature" 
                                   placeholder="Enter your full name" value="{{ $signature->signer_name }}">
                        </div>

                        <!-- Upload Signature -->
                        <div id="signature-uploaded" class="signature-section" style="display: none;">
                            <h6>Upload Signature Image:</h6>
                            <input type="file" class="form-control" id="uploadedSignature" 
                                   accept="image/*">
                            <small class="text-muted">Upload a PNG, JPG, or GIF image</small>
                        </div>

                        <!-- Signature Preview -->
                        <div id="signature-preview" class="mt-3" style="display: none;">
                            <h6>Signature Preview:</h6>
                            <img id="previewImage" class="signature-preview" alt="Signature Preview">
                        </div>

                        <!-- Fields to Fill -->
                        @if($fields->count() > 0)
                            <div class="mt-4">
                                <h6><i class="fas fa-edit"></i> Fill Required Fields:</h6>
                                @foreach($fields as $field)
                                    <div class="field-container">
                                        <label class="form-label {{ $field->is_required ? 'required-field' : '' }}">
                                            {{ $field->field_label }}
                                        </label>
                                        
                                        @switch($field->field_type)
                                            @case('text')
                                            @case('email')
                                            @case('phone')
                                                <input type="{{ $field->field_type === 'email' ? 'email' : ($field->field_type === 'phone' ? 'tel' : 'text') }}" 
                                                       class="form-control field-input" 
                                                       data-field-id="{{ $field->id }}"
                                                       value="{{ $field->field_value }}"
                                                       {{ $field->is_required ? 'required' : '' }}
                                                       {{ $field->is_readonly ? 'readonly' : '' }}>
                                                @break
                                                
                                            @case('textarea')
                                                <textarea class="form-control field-input" 
                                                          data-field-id="{{ $field->id }}"
                                                          rows="3"
                                                          {{ $field->is_required ? 'required' : '' }}
                                                          {{ $field->is_readonly ? 'readonly' : '' }}>{{ $field->field_value }}</textarea>
                                                @break
                                                
                                            @case('date')
                                                <input type="date" class="form-control field-input" 
                                                       data-field-id="{{ $field->id }}"
                                                       value="{{ $field->field_value }}"
                                                       {{ $field->is_required ? 'required' : '' }}
                                                       {{ $field->is_readonly ? 'readonly' : '' }}>
                                                @break
                                                
                                            @case('checkbox')
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input field-input" 
                                                           data-field-id="{{ $field->id }}"
                                                           {{ $field->field_value ? 'checked' : '' }}
                                                           {{ $field->is_readonly ? 'disabled' : '' }}>
                                                    <label class="form-check-label">{{ $field->field_label }}</label>
                                                </div>
                                                @break
                                                
                                            @case('dropdown')
                                                <select class="form-control field-input" 
                                                        data-field-id="{{ $field->id }}"
                                                        {{ $field->is_required ? 'required' : '' }}
                                                        {{ $field->is_readonly ? 'disabled' : '' }}>
                                                    <option value="">Select an option</option>
                                                    @foreach($field->getFieldOptions() as $option)
                                                        <option value="{{ $option }}" 
                                                                {{ $field->field_value === $option ? 'selected' : '' }}>
                                                            {{ $option }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break
                                        @endswitch
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="mt-4">
                            <button type="button" class="btn btn-success btn-lg w-100" id="signDocument">
                                <i class="fas fa-signature"></i> Sign Document
                            </button>
                            
                            <button type="button" class="btn btn-outline-danger btn-lg w-100 mt-2" id="declineDocument">
                                <i class="fas fa-times"></i> Decline to Sign
                            </button>
                        </div>

                        <!-- Security Notice -->
                        <div class="alert alert-light mt-3">
                            <small>
                                <i class="fas fa-shield-alt"></i> 
                                <strong>Security Notice:</strong> Your signature and IP address will be recorded for legal purposes.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div class="modal fade" id="declineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Decline to Sign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to decline signing this document?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason (optional):</label>
                        <textarea class="form-control" id="declineReason" rows="3" 
                                  placeholder="Please provide a reason for declining..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDecline">Decline</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let signaturePad;
        let currentSignatureType = 'drawn';
        let signatureData = null;

        // Initialize signature pad
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('signatureCanvas');
            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: 'rgb(0, 0, 0)'
            });

            // Handle signature type selection
            document.querySelectorAll('.btn-signature-type').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.btn-signature-type').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Hide all signature sections
                    document.querySelectorAll('.signature-section').forEach(section => {
                        section.style.display = 'none';
                    });
                    
                    // Show selected signature section
                    currentSignatureType = this.dataset.type;
                    document.getElementById('signature-' + currentSignatureType).style.display = 'block';
                });
            });

            // Clear signature
            document.getElementById('clearSignature').addEventListener('click', function() {
                signaturePad.clear();
            });

            // Handle file upload
            document.getElementById('uploadedSignature').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('previewImage').src = e.target.result;
                        document.getElementById('signature-preview').style.display = 'block';
                        signatureData = e.target.result.split(',')[1]; // Remove data:image/...;base64, prefix
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Sign document
            document.getElementById('signDocument').addEventListener('click', function() {
                if (!validateSignature()) {
                    return;
                }

                const fields = getFieldValues();
                const signature = getSignatureData();

                // Show loading
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing...';
                this.disabled = true;

                // Submit signature
                fetch('{{ route("esignature.public.submit", $signature->signature_token) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        signature_data: signature,
                        signature_type: currentSignatureType,
                        fields: fields
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect_url;
                    } else {
                        alert(data.message || 'Failed to sign document');
                        this.innerHTML = '<i class="fas fa-signature"></i> Sign Document';
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while signing the document');
                    this.innerHTML = '<i class="fas fa-signature"></i> Sign Document';
                    this.disabled = false;
                });
            });

            // Decline document
            document.getElementById('declineDocument').addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('declineModal'));
                modal.show();
            });

            document.getElementById('confirmDecline').addEventListener('click', function() {
                const reason = document.getElementById('declineReason').value;
                
                fetch('{{ route("esignature.public.decline", $signature->signature_token) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect_url;
                    } else {
                        alert(data.message || 'Failed to decline document');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while declining the document');
                });
            });
        });

        function validateSignature() {
            switch (currentSignatureType) {
                case 'drawn':
                    if (signaturePad.isEmpty()) {
                        alert('Please draw your signature');
                        return false;
                    }
                    break;
                case 'typed':
                    const typedSignature = document.getElementById('typedSignature').value.trim();
                    if (!typedSignature) {
                        alert('Please enter your typed signature');
                        return false;
                    }
                    break;
                case 'uploaded':
                    if (!signatureData) {
                        alert('Please upload a signature image');
                        return false;
                    }
                    break;
            }
            return true;
        }

        function getSignatureData() {
            switch (currentSignatureType) {
                case 'drawn':
                    return signaturePad.toDataURL().split(',')[1]; // Remove data:image/...;base64, prefix
                case 'typed':
                    return document.getElementById('typedSignature').value.trim();
                case 'uploaded':
                    return signatureData;
                default:
                    return null;
            }
        }

        function getFieldValues() {
            const fields = [];
            document.querySelectorAll('.field-input').forEach(input => {
                let value;
                if (input.type === 'checkbox') {
                    value = input.checked;
                } else {
                    value = input.value;
                }
                
                fields.push({
                    field_id: input.dataset.fieldId,
                    value: value
                });
            });
            return fields;
        }
    </script>
</body>
</html>
