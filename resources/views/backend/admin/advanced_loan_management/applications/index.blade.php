@extends('layouts.app')

@section('title', 'Advanced Loan Applications')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('advanced_loan_management.index') }}">Advanced Loan Management</a></li>
                        <li class="breadcrumb-item active">Applications</li>
                    </ol>
                </div>
                <h4 class="page-title">Advanced Loan Applications</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <select id="status_filter" class="form-control">
                                <option value="">All Status</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="under_review">Under Review</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="disbursed">Disbursed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="application_type_filter" class="form-control">
                                <option value="">All Types</option>
                                <option value="business_loan">Business Loan</option>
                                <option value="value_addition_enterprise">Value Addition Enterprise</option>
                                <option value="startup_loan">Startup Loan</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="date" id="date_from_filter" class="form-control" placeholder="Date From">
                        </div>
                        <div class="col-md-3">
                            <input type="date" id="date_to_filter" class="form-control" placeholder="Date To">
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="applications_table" class="table table-striped table-bordered dt-responsive nowrap" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th>Application #</th>
                                    <th>Applicant</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
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
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Application</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="approveForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="approved_amount">Approved Amount (KES) <span class="text-danger">*</span></label>
                        <input type="number" id="approved_amount" name="approved_amount" class="form-control" required min="1000">
                    </div>
                    <div class="form-group">
                        <label for="review_notes">Review Notes</label>
                        <textarea id="review_notes" name="review_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Application</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea id="rejection_reason" name="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Disbursement Modal -->
<div class="modal fade" id="disburseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Disburse Loan</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="disburseForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="disbursement_account_id">Disbursement Account <span class="text-danger">*</span></label>
                        <select id="disbursement_account_id" name="disbursement_account_id" class="form-control" required>
                            <option value="">Select Account</option>
                            <!-- Populate with bank accounts -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="disbursement_date">Disbursement Date <span class="text-danger">*</span></label>
                        <input type="date" id="disbursement_date" name="disbursement_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="loan_term_months">Loan Term (Months) <span class="text-danger">*</span></label>
                        <input type="number" id="loan_term_months" name="loan_term_months" class="form-control" required min="1" max="60">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Disburse Loan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#applications_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("advanced_loan_management.applications.data") }}',
            data: function(d) {
                d.status = $('#status_filter').val();
                d.application_type = $('#application_type_filter').val();
                d.date_from = $('#date_from_filter').val();
                d.date_to = $('#date_to_filter').val();
            }
        },
        columns: [
            { data: 'application_number', name: 'application_number' },
            { data: 'applicant_name', name: 'applicant_name' },
            { data: 'loanProduct.name', name: 'loan_product_id' },
            { data: 'application_type', name: 'application_type' },
            { data: 'requested_amount', name: 'requested_amount' },
            { data: 'status', name: 'status' },
            { data: 'application_date', name: 'application_date' },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[6, 'desc']],
        pageLength: 25,
        responsive: true,
        language: {
            processing: "Loading applications...",
            emptyTable: "No applications found",
            zeroRecords: "No matching applications found"
        }
    });

    // Filter change events
    $('#status_filter, #application_type_filter, #date_from_filter, #date_to_filter').on('change', function() {
        table.ajax.reload();
    });

    // Approval functionality
    $(document).on('click', '.approve-application', function(e) {
        e.preventDefault();
        var applicationId = $(this).data('id');
        $('#approveForm').data('id', applicationId);
        $('#approveModal').modal('show');
    });

    $('#approveForm').on('submit', function(e) {
        e.preventDefault();
        var applicationId = $(this).data('id');
        var formData = $(this).serialize();

        $.ajax({
            url: '{{ route("advanced_loan_management.applications.approve", ":id") }}'.replace(':id', applicationId),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#approveModal').modal('hide');
                    table.ajax.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                alert('An error occurred while approving the application.');
            }
        });
    });

    // Rejection functionality
    $(document).on('click', '.reject-application', function(e) {
        e.preventDefault();
        var applicationId = $(this).data('id');
        $('#rejectForm').data('id', applicationId);
        $('#rejectModal').modal('show');
    });

    $('#rejectForm').on('submit', function(e) {
        e.preventDefault();
        var applicationId = $(this).data('id');
        var formData = $(this).serialize();

        $.ajax({
            url: '{{ route("advanced_loan_management.applications.reject", ":id") }}'.replace(':id', applicationId),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#rejectModal').modal('hide');
                    table.ajax.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                alert('An error occurred while rejecting the application.');
            }
        });
    });

    // Disbursement functionality
    $(document).on('click', '.disburse-loan', function(e) {
        e.preventDefault();
        var applicationId = $(this).data('id');
        $('#disburseForm').data('id', applicationId);
        
        // Load bank accounts
        $.get('{{ route("advanced_loan_management.bank_accounts") }}', function(data) {
            $('#disbursement_account_id').empty().append('<option value="">Select Account</option>');
            $.each(data, function(index, account) {
                $('#disbursement_account_id').append('<option value="' + account.id + '">' + account.account_name + ' (' + account.account_number + ')</option>');
            });
        });

        $('#disburseModal').modal('show');
    });

    $('#disburseForm').on('submit', function(e) {
        e.preventDefault();
        var applicationId = $(this).data('id');
        var formData = $(this).serialize();

        $.ajax({
            url: '{{ route("advanced_loan_management.applications.disburse", ":id") }}'.replace(':id', applicationId),
            type: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    $('#disburseModal').modal('hide');
                    table.ajax.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                alert('An error occurred while disbursing the loan.');
            }
        });
    });

    // Set today's date as default for disbursement date
    $('#disbursement_date').val(new Date().toISOString().split('T')[0]);
});
</script>
@endpush
