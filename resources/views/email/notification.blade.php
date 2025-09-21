@component('mail::message')

{!! xss_clean($message) !!}<br>

{{ _lang('Regards') }},<br>
@if(isset(request()->tenant))
{{ get_tenant_option('business_name', get_option('site_title', config('app.name'))) }}
@elseif(isset($tenant))
{{ get_tenant_option('business_name', get_option('site_title', config('app.name')), $tenant->id) }}
@else
{{ get_option('site_title', config('app.name')) }}
@endif
@endcomponent