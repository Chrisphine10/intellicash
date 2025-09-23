@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <div class="panel-title">{{ _lang('Document Management') }}</div>
                <div>
                    <a class="btn btn-primary btn-xs" href="{{ route('documents.create') }}">
                        <i class="fas fa-plus mr-1"></i>{{ _lang('Upload Document') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="row mb-3">
                    <div class="col-lg-3">
                        <select class="form-control" id="category_filter">
                            <option value="all">{{ _lang('All Categories') }}</option>
                            <option value="terms_and_conditions" {{ $category == 'terms_and_conditions' ? 'selected' : '' }}>{{ _lang('Terms and Conditions') }}</option>
                            <option value="privacy_policy" {{ $category == 'privacy_policy' ? 'selected' : '' }}>{{ _lang('Privacy Policy') }}</option>
                            <option value="loan_agreement" {{ $category == 'loan_agreement' ? 'selected' : '' }}>{{ _lang('Loan Agreement') }}</option>
                            <option value="legal_document" {{ $category == 'legal_document' ? 'selected' : '' }}>{{ _lang('Legal Document') }}</option>
                            <option value="policy" {{ $category == 'policy' ? 'selected' : '' }}>{{ _lang('Policy') }}</option>
                            <option value="other" {{ $category == 'other' ? 'selected' : '' }}>{{ _lang('Other') }}</option>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <input type="text" class="form-control" id="search_input" placeholder="{{ _lang('Search documents...') }}">
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary" id="search_btn">
                            <i class="fas fa-search mr-1"></i>{{ _lang('Search') }}
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="documents_table" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{ _lang('Title') }}</th>
                                <th>{{ _lang('Category') }}</th>
                                <th>{{ _lang('File Size') }}</th>
                                <th>{{ _lang('Version') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Visibility') }}</th>
                                <th>{{ _lang('Created') }}</th>
                                <th>{{ _lang('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mt-4">
    <div class="col-lg-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0" id="total_documents">0</h4>
                        <p class="mb-0">{{ _lang('Total Documents') }}</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0" id="active_documents">0</h4>
                        <p class="mb-0">{{ _lang('Active Documents') }}</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0" id="public_documents">0</h4>
                        <p class="mb-0">{{ _lang('Public Documents') }}</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-globe fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0" id="terms_documents">0</h4>
                        <p class="mb-0">{{ _lang('Terms & Conditions') }}</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-gavel fa-2x"></i>
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
    var table = $('#documents_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('documents.data') }}",
            data: function(d) {
                d.category = $('#category_filter').val();
                d.search = $('#search_input').val();
            }
        },
        columns: [
            { data: 'title', name: 'title' },
            { data: 'category', name: 'category' },
            { data: 'file_size', name: 'file_size' },
            { data: 'version', name: 'version' },
            { data: 'is_active', name: 'is_active' },
            { data: 'is_public', name: 'is_public' },
            { data: 'created_at', name: 'created_at' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        pageLength: 25,
        responsive: true,
        language: {
            processing: "{{ _lang('Processing...') }}",
            search: "{{ _lang('Search') }}",
            lengthMenu: "{{ _lang('Show _MENU_ entries') }}",
            info: "{{ _lang('Showing _START_ to _END_ of _TOTAL_ entries') }}",
            infoEmpty: "{{ _lang('No entries found') }}",
            infoFiltered: "{{ _lang('(filtered from _MAX_ total entries)') }}",
            paginate: {
                first: "{{ _lang('First') }}",
                last: "{{ _lang('Last') }}",
                next: "{{ _lang('Next') }}",
                previous: "{{ _lang('Previous') }}"
            }
        }
    });

    // Category filter
    $('#category_filter').change(function() {
        table.ajax.reload();
    });

    // Search
    $('#search_btn').click(function() {
        table.ajax.reload();
    });

    // Search on Enter key
    $('#search_input').keypress(function(e) {
        if (e.which == 13) {
            table.ajax.reload();
        }
    });

    // Update stats
    table.on('draw', function() {
        updateStats();
    });

    function updateStats() {
        $.ajax({
            url: "{{ route('documents.stats') }}",
            method: 'GET',
            success: function(data) {
                $('#total_documents').text(data.total);
                $('#active_documents').text(data.active);
                $('#public_documents').text(data.public);
                $('#terms_documents').text(data.terms);
            }
        });
    }

    // Initial stats load
    updateStats();
});

// Delete document function
function deleteDocument(id) {
    Swal.fire({
        title: '{{ _lang("Are you sure?") }}',
        text: '{{ _lang("You will not be able to recover this document!") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: '{{ _lang("Yes, delete it!") }}',
        cancelButtonText: '{{ _lang("Cancel") }}'
    }).then((result) => {
        if (result.value) {
            $.ajax({
                url: '/documents/' + id,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#documents_table').DataTable().ajax.reload();
                        Swal.fire('{{ _lang("Deleted!") }}', response.message, 'success');
                    } else {
                        Swal.fire('{{ _lang("Error!") }}', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('{{ _lang("Error!") }}', '{{ _lang("Something went wrong!") }}', 'error');
                }
            });
        }
    });
}
</script>
@endsection
