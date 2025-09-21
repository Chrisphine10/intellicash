@php $custom_js_code = isset($header_footer_settings->custom_js) ? $header_footer_settings->custom_js : ''; @endphp

<script type="text/javascript">
{!! xss_clean($custom_js_code) !!}
</script>