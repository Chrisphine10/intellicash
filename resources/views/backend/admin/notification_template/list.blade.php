@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Notification Templates') }}</span>
			</div>
			<div class="card-body">
				<table class="table data-table">
					<thead>
						<tr>
							<th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Allowed Channels') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($emailtemplates as $emailtemplate)
						<tr id="row_{{ $emailtemplate->id }}">
							<td class='name'>{{ ucwords(str_replace('_',' ',$emailtemplate->name)) }}</td>
							<td class='status'>
								@if($emailtemplate->email_status == 1)
								{!! xss_clean(show_status(_lang('Email'), 'primary')) !!}
								@endif

								@if($emailtemplate->sms_status == 1)
								{!! xss_clean(show_status(_lang('SMS'), 'primary')) !!}
								@endif

								@if($emailtemplate->notification_status == 1)
								{!! xss_clean(show_status(_lang('App'), 'primary')) !!}
								@endif

								@if($emailtemplate->email_status == 0 && $emailtemplate->sms_status == 0 && $emailtemplate->notification_status == 0)
								{!! xss_clean(show_status(_lang('N/A'), 'secondary')) !!}
								@endif
							</td>
							<td class="text-center">
								<a href="{{ route('email_templates.edit', $emailtemplate->id) }}" class="btn btn-primary btn-xs"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection