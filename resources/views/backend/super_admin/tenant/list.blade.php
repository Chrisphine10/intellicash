@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center justify-content-between">
				<span class="panel-title">{{ _lang('Tenants') }}</span>
				<a class="btn btn-primary btn-xs" data-title="{{ _lang('Add Tenant') }}" href="{{ route('admin.tenants.create') }}"><i class="ti-plus"></i> {{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="tenants_table" class="table table-bordered">
					<thead>
					    <tr>
                            <th>{{ _lang('Name') }}</th>
                            <th>{{ _lang('Plan') }}</th>
                            <th>{{ _lang('Membership') }}</th>
                            <th class="text-center">{{ _lang('Status') }}</th>
                            <th>{{ _lang('Expiration') }}</th>
                            <th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {
	"use strict";

	$('#tenants_table').DataTable({
		processing: true,
		serverSide: true,
		ajax: '{{ url('admin/tenants/get_table_data') }}',
		"columns" : [
			{ data : 'name', name : 'name' },
			{ data : 'package.name', name : 'package.name' },
			{ data : 'membership_type', name : 'membership_type' },
			{ data : 'status', name : 'status' },
			{ data : 'valid_to', name : 'valid_to' },
			{ data : "action", name : "action" },
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,
		"ordering": false,
		"language": {
		   "decimal":        "",
		   "emptyTable":     "{{ _lang('No Data Found') }}",
		   "info":           "{{ _lang('Showing') }} _START_ {{ _lang('to') }} _END_ {{ _lang('of') }} _TOTAL_ {{ _lang('Entries') }}",
		   "infoEmpty":      "{{ _lang('Showing 0 To 0 Of 0 Entries') }}",
		   "infoFiltered":   "(filtered from _MAX_ total entries)",
		   "infoPostFix":    "",
		   "thousands":      ",",
		   "lengthMenu":     "{{ _lang('Show') }} _MENU_ {{ _lang('Entries') }}",
		   "loadingRecords": "{{ _lang('Loading...') }}",
		   "processing":     "{{ _lang('Processing...') }}",
		   "search":         "{{ _lang('Search') }}",
		   "zeroRecords":    "{{ _lang('No matching records found') }}",
		   "paginate": {
			  "first":      "{{ _lang('First') }}",
			  "last":       "{{ _lang('Last') }}",
			  "previous":   "<i class='fas fa-angle-left'></i>",
			  "next":       "<i class='fas fa-angle-right'></i>"
		  }
		}
	});
})(jQuery);
</script>
@endsection