<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>{{ !isset($page_title) ? get_option('site_title', config('app.name')) : $page_title }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        @if(get_option('pwa_enabled', 1))
        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="{{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ get_option('pwa_short_name', get_option('company_name', 'IntelliCash')) }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="{{ get_option('pwa_short_name', get_option('company_name', 'IntelliCash')) }}">
        
        <!-- PWA Manifest -->
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        
        <!-- PWA Icons -->
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('public/uploads/media/pwa-icon-180x180.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('public/uploads/media/pwa-icon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('public/uploads/media/pwa-icon-16x16.png') }}">
        <link rel="mask-icon" href="{{ asset('public/uploads/media/pwa-icon.svg') }}" color="{{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }}">
        @endif
        
		<!-- App favicon -->
        <link rel="shortcut icon" href="{{ get_favicon() }}">

		<!-- App Css -->
        <link rel="stylesheet" href="{{ asset('backend/plugins/bootstrap/css/bootstrap.min.css') }}">
		<link rel="stylesheet" href="{{ asset('backend/assets/css/fontawesome.css') }}">
		<link rel="stylesheet" href="{{ asset('backend/assets/css/themify-icons.css') }}">

		@if(isset(request()->tenant->id))
			@if(get_tenant_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap-rtl.min.css') }}">
			@endif
		@else
			@if(get_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap-rtl.min.css') }}">
			@endif
		@endif
		
		<!-- Others css -->
		<link rel="stylesheet" href="{{ asset('backend/assets/css/typography.css') }}">
		<link rel="stylesheet" href="{{ asset('backend/assets/css/default-css.css') }}">
		<link rel="stylesheet" href="{{ asset('backend/assets/css/styles.css?v=1.1') }}">
		<link rel="stylesheet" href="{{ asset('backend/assets/css/responsive.css?v=1.0') }}">
		
		<!-- Modernizr -->
		<script src="{{ asset('backend/assets/js/vendor/modernizr-3.6.0.min.js') }}"></script>     

		@if(isset(request()->tenant->id))
			@if(get_tenant_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/assets/css/rtl/style.css?v=1.0') }}">
			@endif
		@else
			@if(get_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/assets/css/rtl/style.css?v=1.0') }}">
			@endif
		@endif
		
		@include('layouts.others.languages')	
    </head>

    <body class="guest">  
		<!-- Main Modal -->
		<div id="main_modal" class="modal" tabindex="-1" role="dialog">
		    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
				<div class="modal-content">
				    <div class="modal-header">
						<h5 class="modal-title ml-2"></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						  <span aria-hidden="true"><i class="ti-close text-danger"></i></span>
						</button>
				    </div>

				    <div class="alert alert-danger d-none mx-4 mt-3 mb-0"></div>
				    <div class="alert alert-primary d-none mx-4 mt-3 mb-0"></div>
				    <div class="modal-body overflow-hidden"></div>

				</div>
		    </div>
		</div>
	     
		<div class="container my-5">						
			<div class="row">
				<div class="{{ isset($alert_col) ? $alert_col : 'col-lg-12' }}">
					<div class="alert alert-success alert-dismissible" id="main_alert" role="alert">
						<button type="button" id="close_alert" class="close">
							<span aria-hidden="true"><i class="far fa-times-circle"></i></span>
						</button>
						<span class="msg"></span>
					</div>		
				</div>
			</div>		
			
			@if(session('login_as_user') == true && session('admin') != null)
			<div class="row">
				<div class="{{ isset($alert_col) ? $alert_col : 'col-lg-12' }}">
					<div class="alert alert-warning" role="alert">
						<span><i class="fas fa-info-circle mr-2"></i>{{ _lang('Back to admin portal?') }} <a href="{{ route('users.back_to_admin') }}">{{ _lang('Click Here') }}</a></span>
					</div>
				</div>
			</div>
			@endif

			@yield('content')
		</div>


        <!-- jQuery  -->
		<script src="{{ asset('backend/assets/js/vendor/jquery-3.7.1.min.js') }}"></script>
		<script src="{{ asset('backend/assets/js/popper.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/bootstrap/js/bootstrap.min.js') }}"></script> 
		<script src="{{ asset('backend/assets/js/print.js') }}"></script>
		<script src="{{ asset('backend/assets/js/guest.js') }}"></script>

		@if(get_option('pwa_enabled', 1))
		<!-- PWA Service Worker Registration -->
		<script>
			if ('serviceWorker' in navigator) {
				window.addEventListener('load', function() {
					navigator.serviceWorker.register('/sw.js')
						.then(function(registration) {
							console.log('ServiceWorker registration successful');
						})
						.catch(function(err) {
							console.log('ServiceWorker registration failed: ', err);
						});
				});
			}
		</script>
		@endif

		@include('layouts.others.alert')
		 
		<!-- Custom JS -->
		@yield('js-script')	
    </body>
</html>