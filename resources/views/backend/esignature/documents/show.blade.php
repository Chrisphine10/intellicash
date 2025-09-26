@extends('layouts.app')

@section('title', 'E-Signature Document Details')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <!-- Back Button -->
        <div class="mb-3">
            <a href="{{ route('esignature.esignature-documents.index') }}" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Back to Documents
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="card-title">{{ $document->title }}</h4>
                        <p class="mb-0 text-muted">{{ $document->description }}</p>
                    </div>
                    <div class="col-md-4 text-right">
                        <!-- Document Actions -->
                        <div class="btn-group mb-2" role="group">
                            <!-- Edit Actions -->
                            @if($document->status === 'Draft')
                                <a href="{{ route('esignature.esignature-documents.edit', $document->id) }}" class="btn btn-warning btn-sm">
                                    <i class="fa fa-edit"></i> Edit
                                </a>
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#sendModal">
                                    <i class="fa fa-paper-plane"></i> Send
                                </button>
                            @elseif($document->status === 'Sent' || $document->status === 'Expired')
                                <a href="{{ route('esignature.esignature-documents.edit', $document->id) }}" class="btn btn-warning btn-sm">
                                    <i class="fa fa-edit"></i> Edit Details
                                </a>
                                @if($document->status === 'Sent')
                                    <button type="button" class="btn btn-info btn-sm" onclick="sendReminders({{ $document->id }})">
                                        <i class="fa fa-bell"></i> Remind
                                    </button>
                                @endif
                            @endif
                            
                            <!-- Cancel Action -->
                            @if($document->status === 'Sent' || $document->status === 'Draft')
                                <button type="button" class="btn btn-danger btn-sm" onclick="cancelDocument()">
                                    <i class="fa fa-times"></i> Cancel
                                </button>
                            @endif
                        </div>
                        
                        <!-- View & Download Actions -->
                        <div class="btn-group" role="group">
                            <a href="{{ route('esignature.esignature-documents.view', $document->id) }}" class="btn btn-info btn-sm" target="_blank">
                                <i class="fa fa-eye"></i> View
                            </a>
                            
                            <a href="{{ route('esignature.esignature-documents.download', $document->id) }}" class="btn btn-secondary btn-sm">
                                <i class="fa fa-download"></i> Download
                            </a>
                            
                            @if($document->isCompleted())
                                <a href="{{ route('esignature.esignature-documents.download-signed', $document->id) }}" class="btn btn-success btn-sm">
                                    <i class="fa fa-file-signature"></i> Signed Copy
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Document Status -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>{{ ucfirst($document->status) }}</h5>
                                <small>Status</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>{{ $document->getCompletedSignaturesCount() }}/{{ $document->getSignerCount() }}</h5>
                                <small>Signatures</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>{{ ucfirst($document->document_type) }}</h5>
                                <small>Type</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h5>{{ $document->getFormattedFileSize() }}</h5>
                                <small>File Size</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Information -->
                <div class="row">
                    <div class="col-md-6">
                        <h5>Document Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Title:</strong></td>
                                <td>{{ $document->title }}</td>
                            </tr>
                            <tr>
                                <td><strong>Description:</strong></td>
                                <td>{{ $document->description ?: 'No description' }}</td>
                            </tr>
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td>{{ ucfirst($document->document_type) }}</td>
                            </tr>
                            <tr>
                                <td><strong>File:</strong></td>
                                <td>{{ $document->file_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td>{{ $document->created_at->format('M d, Y H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Created by:</strong></td>
                                <td>{{ $document->creator->name }}</td>
                            </tr>
                            @if($document->sent_at)
                                <tr>
                                    <td><strong>Sent:</strong></td>
                                    <td>{{ $document->sent_at->format('M d, Y H:i') }}</td>
                                </tr>
                            @endif
                            @if($document->expires_at)
                                <tr>
                                    <td><strong>Expires:</strong></td>
                                    <td class="{{ $document->isExpired() ? 'text-danger' : 'text-muted' }}">
                                        {{ $document->expires_at->format('M d, Y H:i') }}
                                    </td>
                                </tr>
                            @endif
                            @if($document->completed_at)
                                <tr>
                                    <td><strong>Completed:</strong></td>
                                    <td>{{ $document->completed_at->format('M d, Y H:i') }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Sender Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>{{ $document->sender_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>{{ $document->sender_email }}</td>
                            </tr>
                            @if($document->sender_company)
                                <tr>
                                    <td><strong>Company:</strong></td>
                                    <td>{{ $document->sender_company }}</td>
                                </tr>
                            @endif
                            @if($document->custom_message)
                                <tr>
                                    <td><strong>Message:</strong></td>
                                    <td>{{ $document->custom_message }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>

                <!-- Signers -->
                <div class="mt-4">
                    <h5>Signers</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Signed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($document->signatures as $signature)
                                <tr>
                                    <td>{{ $signature->signer_name }}</td>
                                    <td>{{ $signature->signer_email }}</td>
                                    <td>{{ $signature->signer_phone ?: '-' }}</td>
                                    <td>
                                        @switch($signature->status)
                                            @case('pending')
                                                <span class="badge badge-warning">Pending</span>
                                                @break
                                            @case('signed')
                                                <span class="badge badge-success">Signed</span>
                                                @break
                                            @case('declined')
                                                <span class="badge badge-danger">Declined</span>
                                                @break
                                            @case('expired')
                                                <span class="badge badge-secondary">Expired</span>
                                                @break
                                            @case('cancelled')
                                                <span class="badge badge-secondary">Cancelled</span>
                                                @break
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($signature->signed_at)
                                            {{ $signature->signed_at->format('M d, Y H:i') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($signature->status === 'pending')
                                            <a href="{{ $signature->getSignatureUrl() }}" class="btn btn-sm btn-info" target="_blank">
                                                <i class="fa fa-external-link"></i> View Link
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Audit Trail -->
                <div class="mt-4">
                    <h5>Audit Trail</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Actor</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($document->auditTrail as $audit)
                                <tr>
                                    <td>{{ $audit->getActionDescription() }}</td>
                                    <td>{{ $audit->getActorDisplayName() }}</td>
                                    <td>{{ $audit->description }}</td>
                                    <td>{{ $audit->getFormattedTimestamp() }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send Modal -->
<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Document for Signing</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('esignature.esignature-documents.send', $document->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        This will send the document to all signers for electronic signature.
                    </div>
                    
                    <div id="signers-container">
                        @foreach($document->signers as $index => $signer)
                        <div class="signer-row row mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="signers[{{ $index }}][name]" 
                                       value="{{ $signer['name'] }}" placeholder="Full Name" required>
                            </div>
                            <div class="col-md-3">
                                <input type="email" class="form-control" name="signers[{{ $index }}][email]" 
                                       value="{{ $signer['email'] }}" placeholder="Email Address" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="signers[{{ $index }}][phone]" 
                                       value="{{ $signer['phone'] ?? '' }}" placeholder="Phone (Optional)">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="signers[{{ $index }}][company]" 
                                       value="{{ $signer['company'] ?? '' }}" placeholder="Company (Optional)">
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send for Signing</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function cancelDocument() {
    if (confirm('Are you sure you want to cancel this document? This action cannot be undone.')) {
        fetch('{{ route("esignature.esignature-documents.cancel", $document->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to cancel document');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cancelling the document');
        });
    }

    // Send reminders function
    function sendReminders(documentId) {
        if (!confirm('Send reminder notifications to all pending signers?')) {
            return;
        }

        fetch(`{{ url(app('tenant')->slug . '/esignature/esignature-documents') }}/${documentId}/send-reminders`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Reminders sent successfully');
            } else {
                alert('Failed to send reminders');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send reminders');
        });
    }
}
</script>
@endpush
