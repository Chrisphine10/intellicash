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
						@php
							$isVslaTemplate = in_array($emailtemplate->name, ['VSLA Cycle Report', 'VSLA Meeting Reminder']);
							$showTemplate = true;
							
							// Hide VSLA templates if VSLA module is not enabled
							if ($isVslaTemplate && !app('tenant')->isVslaEnabled()) {
								$showTemplate = false;
							}
						@endphp
						
						@if($showTemplate)
						<tr id="row_{{ $emailtemplate->id }}" class="{{ $isVslaTemplate ? 'vsla-template' : '' }}">
							<td class='name'>
								{{ ucwords(str_replace('_',' ',$emailtemplate->name)) }}
								@if($isVslaTemplate)
									<span class="badge badge-info badge-sm ml-2">VSLA</span>
								@endif
							</td>
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
								<a href="{{ route('email_templates.edit', $emailtemplate->id) }}" class="btn btn-primary btn-xs">
									<i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}
								</a>
								@if($isVslaTemplate)
									<small class="text-muted d-block mt-1">{{ _lang('VSLA Module Required') }}</small>
								@endif
							</td>
						</tr>
						@endif
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@section('css-script')
<style>
.vsla-template {
    background-color: #f8f9fa;
    border-left: 4px solid #17a2b8;
}

.vsla-template td {
    border-color: #e9ecef;
}

.badge-sm {
    font-size: 0.75em;
    padding: 0.25em 0.5em;
}

@media (max-width: 768px) {
    .vsla-template .badge {
        display: block;
        margin-top: 5px;
        margin-left: 0 !important;
    }
}
</style>
@endsection