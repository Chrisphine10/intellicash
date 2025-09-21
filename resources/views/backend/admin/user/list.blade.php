@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="panel-title">{{ _lang('Users') }}</span>
                <a class="btn btn-primary btn-xs ml-auto" href="{{ route('users.create') }}"><i class="ti-plus mr-1"></i>{{ _lang('Add New') }}</a>
            </div>

            <div class="card-body">
                <table id="users_table" class="table">
                    <thead>
                        <tr>
                            <th>{{ _lang('Name') }}</th>
                            <th>{{ _lang('Email') }}</th>
							<th>{{ _lang('User Type') }}</th>
                            <th>{{ _lang('Role') }}</th>
                            <th>{{ _lang('Status') }}</th>
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

	$('#users_table').DataTable({
		processing: true,
		serverSide: true,
		ajax: _tenant_url + '/users/get_table_data',
		"columns" : [
			{ data : 'name', name : 'name' },
			{ data : 'email', name : 'email' },
			{ data : 'user_type', name : 'user_type' },
			{ data : 'role.name', name : 'role.name', defaultContent: '' },
			{ data : 'status', name : 'status' },
			{ data : "action", name : "action" },
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,
		//"ordering": false,
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