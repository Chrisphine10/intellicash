@extends('layouts.app')

@section('title', 'E-Signature Documents')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="card-title">E-Signature Documents</h4>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="{{ route('esignature.esignature-documents.create') }}" class="btn btn-primary">
                            <i class="fa fa-plus"></i> Create Document
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select class="form-control" id="status-filter">
                            <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="signed" {{ request('status') == 'signed' ? 'selected' : '' }}>Signed</option>
                            <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="type-filter">
                            <option value="all" {{ request('type') == 'all' || !request('type') ? 'selected' : '' }}>All Types</option>
                            <option value="contract" {{ request('type') == 'contract' ? 'selected' : '' }}>Contract</option>
                            <option value="agreement" {{ request('type') == 'agreement' ? 'selected' : '' }}>Agreement</option>
                            <option value="form" {{ request('type') == 'form' ? 'selected' : '' }}>Form</option>
                            <option value="policy" {{ request('type') == 'policy' ? 'selected' : '' }}>Policy</option>
                            <option value="other" {{ request('type') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="search-input" placeholder="Search documents..." value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-secondary" id="filter-btn">Filter</button>
                        <button class="btn btn-outline-secondary ml-1" id="clear-btn">Clear</button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5>{{ $stats['total'] }}</h5>
                                <small>Total Documents</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5>{{ $stats['sent'] }}</h5>
                                <small>Pending</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5>{{ $stats['signed'] }}</h5>
                                <small>Completed</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5>{{ $stats['draft'] }}</h5>
                                <small>Draft</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5>{{ $stats['expired'] }}</h5>
                                <small>Expired</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body text-center">
                                <h5>{{ $stats['cancelled'] }}</h5>
                                <small>Cancelled</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documents Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Signers</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documents as $document)
                            <tr>
                                <td>
                                    <div>
                                        <strong>{{ $document->title }}</strong>
                                        @if($document->description)
                                            <br><small class="text-muted">{{ Str::limit($document->description, 50) }}</small>
                                        @endif
                                        <br><small class="text-muted">by {{ $document->creator->name }}</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info">{{ ucfirst($document->document_type) }}</span>
                                </td>
                                <td>
                                    @switch($document->status)
                                        @case('draft')
                                            <span class="badge badge-warning">Draft</span>
                                            @break
                                        @case('sent')
                                            <span class="badge badge-info">Sent</span>
                                            @break
                                        @case('signed')
                                            <span class="badge badge-success">Signed</span>
                                            @break
                                        @case('expired')
                                            <span class="badge badge-danger">Expired</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge badge-secondary">Cancelled</span>
                                            @break
                                    @endswitch
                                </td>
                                <td>
                                    <div>
                                        <strong>{{ $document->getCompletedSignaturesCount() }}/{{ $document->getSignerCount() }}</strong>
                                        @if($document->getSignerCount() > 0)
                                            <div class="progress mt-1" style="height: 5px;">
                                                <div class="progress-bar" style="width: {{ ($document->getCompletedSignaturesCount() / $document->getSignerCount()) * 100 }}%"></div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <small>{{ $document->created_at->format('M d, Y') }}</small>
                                </td>
                                <td>
                                    @if($document->expires_at)
                                        <small class="{{ $document->isExpired() ? 'text-danger' : 'text-muted' }}">
                                            {{ $document->expires_at->format('M d, Y') }}
                                        </small>
                                    @else
                                        <small class="text-muted">Never</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('esignature.esignature-documents.show', $document->id) }}" 
                                           class="btn btn-sm btn-primary" title="Show Details">
                                            <i class="fa fa-list"></i>
                                        </a>
                                        
                                        @if($document->status === 'draft')
                                            <a href="{{ route('esignature.esignature-documents.edit', $document->id) }}" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        
                                        @if($document->status === 'sent' || $document->status === 'draft')
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="cancelDocument({{ $document->id }})" title="Cancel">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        @endif
                                        
                                        <a href="{{ route('esignature.esignature-documents.view', $document->id) }}" 
                                           class="btn btn-sm btn-info" title="View Document" target="_blank">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        
                                        <a href="{{ route('esignature.esignature-documents.download', $document->id) }}" 
                                           class="btn btn-sm btn-secondary" title="Download">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        
                                        @if($document->isCompleted())
                                            <a href="{{ route('esignature.esignature-documents.download-signed', $document->id) }}" 
                                               class="btn btn-sm btn-success" title="Download Signed">
                                                <i class="fa fa-file-signature"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center">No documents found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $documents->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Document Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Document</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this document? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">No</button>
                <button type="button" class="btn btn-danger" id="confirmCancel">Yes, Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let documentToCancel = null;

function cancelDocument(documentId) {
    documentToCancel = documentId;
    $('#cancelModal').modal('show');
}

$('#confirmCancel').click(function() {
    if (documentToCancel) {
        $.ajax({
            url: `{{ url(app('tenant')->slug . '/esignature/esignature-documents') }}/${documentToCancel}/cancel`,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                location.reload();
            },
            error: function(xhr) {
                alert('Failed to cancel document');
            }
        });
    }
    $('#cancelModal').modal('hide');
});

// Filter functionality
function applyFilters() {
    const status = $('#status-filter').val();
    const type = $('#type-filter').val();
    const search = $('#search-input').val();
    
    let url = new URL(window.location);
    
    if (status && status !== 'all') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    
    if (type && type !== 'all') {
        url.searchParams.set('type', type);
    } else {
        url.searchParams.delete('type');
    }
    
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }
    
    window.location.href = url.toString();
}

$('#filter-btn').click(applyFilters);

// Auto-filter on dropdown change
$('#status-filter, #type-filter').change(applyFilters);

// Auto-filter on enter for search
$('#search-input').keypress(function(e) {
    if (e.which === 13) {
        applyFilters();
    }
});

// Clear filters
$('#clear-btn').click(function() {
    let url = new URL(window.location);
    url.searchParams.delete('status');
    url.searchParams.delete('type');
    url.searchParams.delete('search');
    window.location.href = url.toString();
});
</script>
@endpush
