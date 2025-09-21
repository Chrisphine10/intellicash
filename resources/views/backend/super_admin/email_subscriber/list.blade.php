@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-sm-flex align-items-center justify-content-between">
				<span class="panel-title">{{ _lang('Email Subscribers') }}</span>
                <div>
				    <a class="btn btn-dark btn-xs" href="{{ route('admin.email_subscribers.export') }}"><i class="fas fa-file-excel mr-2"></i>{{ _lang('Export') }}</a>
				    <a class="btn btn-primary btn-xs" href="{{ route('admin.email_subscribers.send_email') }}"><i class="fas fa-paper-plane mr-2"></i>{{ _lang('Send Email') }}</a>
			    </div>
			</div>
			<div class="card-body">
				<table id="email_subscribers_table" class="table">
					<thead>
					    <tr>
						    <th>{{ _lang('Subscribed At') }}</th>
						    <th>{{ _lang('Email Address') }}</th>
						    <th>{{ _lang('Ip Address') }}</th>
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

	$('#email_subscribers_table').DataTable({
		processing: true,
		serverSide: true,
		ajax: '{{ url('admin/email_subscribers/get_table_data') }}',
		"columns" : [
			{ data : 'created_at', name : 'created_at' },
			{ data : 'email_address', name : 'email_address' },
			{ data : 'ip_address', name : 'ip_address' },
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