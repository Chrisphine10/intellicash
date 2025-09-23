@php 
$footer_color = isset($header_footer_settings->footer_color) ? $header_footer_settings->footer_color : 'var(--bs-primary)';
$footer_text_color = isset($header_footer_settings->footer_text_color) ? $header_footer_settings->footer_text_color : '#ffffff';
$custom_css = isset($header_footer_settings->custom_css) ? $header_footer_settings->custom_css : '';
@endphp

<style type="text/css">
    .footer {
        background: var(--bs-primary) !important;
    }
    .footer .about .text, .footer .single-footer h4, .footer .links ul li a, 
    .footer .copyright p, .footer .copyright p a  {
        color: {{ $footer_text_color }} !important;
    }
    
    .footer .links ul li a:hover {
        color: var(--bs-primary) !important;
    }
    
    .footer .about .logo h4:hover {
        color: var(--bs-primary) !important;
    }

    {{ $custom_css }}
</style>