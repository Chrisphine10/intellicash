<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
		@if(app()->bound('tenant'))
        <title>{{ !isset($page_title) ? get_tenant_option('business_name', get_option('site_title', config('app.name'))) : $page_title }}</title>
		@else
        <title>{{ !isset($page_title) ? get_option('site_title', config('app.name')) : $page_title }}</title>
		@endif
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

		@if(get_tenant_option('pwa_enabled', 1))
		<!-- PWA Meta Tags -->
		<meta name="theme-color" content="{{ get_tenant_option('pwa_theme_color', get_tenant_option('primary_color', '#007bff')) }}">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="default">
		<meta name="apple-mobile-web-app-title" content="{{ get_tenant_option('pwa_short_name', get_tenant_option('business_name', 'IntelliCash')) }}">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="application-name" content="{{ get_tenant_option('pwa_short_name', get_tenant_option('business_name', 'IntelliCash')) }}">
		
		<!-- PWA Manifest -->
		<link rel="manifest" href="{{ route('pwa.manifest') }}">
		
		<!-- PWA Icons -->
		<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('public/uploads/media/pwa-icon-180x180.png') }}">
		<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('public/uploads/media/pwa-icon-32x32.png') }}">
		<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('public/uploads/media/pwa-icon-16x16.png') }}">
		<link rel="mask-icon" href="{{ asset('public/uploads/media/pwa-icon.svg') }}" color="{{ get_tenant_option('pwa_theme_color', get_tenant_option('primary_color', '#007bff')) }}">
		@endif

		<!-- App favicon -->
        <link rel="shortcut icon" href="{{ get_favicon() }}">
		<link href="{{ asset('public/backend/plugins/dropify/css/dropify.min.css') }}" rel="stylesheet">
		<link href="{{ asset('public/backend/plugins/sweet-alert2/css/sweetalert2.min.css') }}" rel="stylesheet" type="text/css">
        <link href="{{ asset('public/backend/plugins/animate/animate.css') }}" rel="stylesheet" type="text/css">
		<link href="{{ asset('public/backend/plugins/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
	    <link href="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.css') }}" rel="stylesheet" />
		<link href="{{ asset('public/backend/plugins/daterangepicker/daterangepicker.css') }}" rel="stylesheet" />

		<!-- App Css -->
        <link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap.min.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/fontawesome.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/themify-icons.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/plugins/metisMenu/metisMenu.css') }}">

		@if(isset(request()->tenant->id))
			@if(get_tenant_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap-rtl.min.css') }}">
			@endif
		@else
			@if(get_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap-rtl.min.css') }}">
			@endif
		@endif

		<!-- Conditionals CSS -->
		@include('layouts.others.import-css')

		<!-- Others css -->
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/typography.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/default-css.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/styles.css') . '?v=' . filemtime(public_path('backend/assets/css/styles.css')) }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/responsive.css?v=1.0') }}">

		<!-- Modernizr -->
		<script src="{{ asset('public/backend/assets/js/vendor/modernizr-3.6.0.min.js') }}"></script>

		@if(isset(request()->tenant->id))
			@if(get_tenant_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/assets/css/rtl/style.css?v=1.0') }}">
			@endif
		@else
			@if(get_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/assets/css/rtl/style.css?v=1.0') }}">
			@endif
		@endif

    </head>

    <body>
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

		<!-- Secondary Modal -->
		<div id="secondary_modal" class="modal" tabindex="-1" role="dialog">
		    <div class="modal-dialog modal-dialog-centered" role="document">
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

		<!-- Preloader area start -->
		<div id="preloader">
			<div class="lds-spinner"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>
		</div>
		<!-- Preloader area end -->

		@php $user_type = auth()->check() ? auth()->user()->user_type : null; @endphp

		<div class="page-container">
		    <!-- sidebar menu area start -->
			<div class="sidebar-menu">
				<div class="extra-details">
					<a href="{{ $user_type == 'superadmin' ? route('admin.dashboard.index') : route('dashboard.index') }}">
						<img class="sidebar-logo" src="{{ get_logo() }}" alt="logo">
					</a>
				</div>

				<div class="main-menu">
					<div class="menu-inner">
						<nav>
							<ul class="metismenu {{ $user_type == 'user' ? 'staff-menu' : '' }}" id="menu">
							@if(auth()->check())
								@include('layouts.menus.'.Auth::user()->user_type)
							@endif
							</ul>
						</nav>
					</div>
				</div>
			</div>
			<!-- sidebar menu area end -->

			<!-- main content area start -->
			<div class="main-content">
				<!-- header area start -->
				<div class="header-area">
					<div class="row align-items-center">
						<!-- nav and search button -->
						<div class="col-lg-6 col-4 clearfix rtl-2">
							<div class="nav-btn float-left">
								<span></span>
								<span></span>
								<span></span>
							</div>
						</div>

						<!-- profile info & task notification -->
						<div class="col-lg-6 col-8 clearfix rtl-1">
							<ul class="notification-area float-right d-flex align-items-center">

								@if(auth()->check())
									@if(auth()->user()->user_type == 'customer')
										@php $notifications = Auth::user()->member->notifications->take(15); @endphp
										@php $unreadNotification = Auth::user()->member->unreadNotifications(); @endphp
									@else
										@php $notifications = Auth::user()->notifications->take(15); @endphp
										@php $unreadNotification = Auth::user()->unreadNotifications(); @endphp
									@endif

								<li class="dropdown d-none d-sm-inline-block">
									<i class="ti-bell dropdown-toggle" data-toggle="dropdown">
										<span>{{ $unreadNotification->count() }}</span>
									</i>
									<div class="dropdown-menu bell-notify-box notify-box">
										<span class="notify-title text-center">
											@if($unreadNotification->count() > 0)
											{{ _lang('You have').' '.$unreadNotification->count().' '._lang('new notifications') }}
											@else
											{{ _lang("You don't have any new notification") }}
											@endif
										</span>
										<div class="nofity-list">
											@if($notifications->count() == 0)
												<small class="text-center d-block py-2">{{ _lang('No Notification found') }} !</small>
											@endif

											@foreach ($notifications as $notification)
											<a href="{{ route('profile.show_notification', $notification->id) }}" class="d-flex ajax-modal notify-item" data-title="{{ $notification->data['subject'] }}">
												<div class="notify-thumb {{ $notification->read_at == null ? 'unread-thumb' : '' }}"></div>
												<div class="notify-text {{ $notification->read_at == null ? 'font-weight-bold' : '' }}">
													<p><i class="far fa-bell"></i> {{ $notification->data['subject'] }}</p>
													<p><span>{{ $notification->created_at->diffForHumans() }}</span></p>
												</div>
											</a>
											@endforeach
										</div>
									</div>
								</li>

								<li>
									<div class="user-profile">
										<h4 class="user-name dropdown-toggle" data-toggle="dropdown">
											<img class="avatar user-thumb" id="my-profile-img" src="{{ profile_picture() }}" alt="avatar"> {{ Auth::user()->name }} <i class="fa fa-angle-down"></i>
										</h4>
										<div class="dropdown-menu">
											@if(auth()->user()->user_type == 'customer')
											<a class="dropdown-item" href="{{ route('profile.membership_details') }}"><i class="ti-user text-muted mr-2"></i>{{ _lang('Membership Details') }}</a>
											@endif

											@php $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : ''; @endphp
											<a class="dropdown-item" href="{{ route($isAadminRoute.'profile.edit') }}"><i class="ti-pencil text-muted mr-2"></i>{{ _lang('Profile Settings') }}</a>
											<a class="dropdown-item" href="{{ route($isAadminRoute.'profile.change_password') }}"><i class="ti-exchange-vertical text-muted mr-2"></i></i>{{ _lang('Change Password') }}</a>
											
											@if(auth()->user()->uses_two_factor_auth == 1)
											<a class="dropdown-item" href="{{ route($isAadminRoute.'profile.disable_2fa') }}"><i class="fas fa-key text-muted mr-2"></i>{{ _lang('Disable 2FA') }}</a>
											@else
											<a class="dropdown-item" href="{{ route($isAadminRoute.'profile.enable_2fa') }}"><i class="fas fa-key text-muted mr-2"></i>{{ _lang('Enable 2FA') }}</a>
											@endif

											@if(auth()->user()->user_type == 'admin')
											<a class="dropdown-item" href="{{ route('settings.index') }}"><i class="ti-settings text-muted mr-2"></i>{{ _lang('System Settings') }}</a>
											@endif

											@if(auth()->user()->user_type == 'admin' && auth()->user()->tenant_owner == 1)
											<a class="dropdown-item" href="{{ route('membership.index') }}"><i class="ti-crown text-muted mr-2"></i>{{ _lang('My Subscription') }}</a>
											@endif

											<div class="dropdown-divider"></div>
											<a class="dropdown-item" href="{{ route('logout') }}"><i class="ti-power-off text-muted mr-2"></i>{{ _lang('Logout') }}</a>
										</div>
									</div>
	                            </li>
								@endif

	                        </ul>

						</div>
					</div>
				</div><!-- header area end -->

				<!-- Page title area start -->
				@if(Request::is('dashboard') || Request::is('*/dashboard'))
				<div class="page-title-area">
					<div class="row align-items-center py-3">
						<div class="col-sm-12">
							<div class="d-flex align-items-center justify-content-between">
								<h6>{{ _lang('Dashboard') }}</h6>

								<!--Branch Switcher-->
								@if(auth()->check() && (auth()->user()->user_type == 'admin' || auth()->user()->all_branch_access == 1))
								<div class="dropdown">
									<a class="dropdown-toggle btn btn-dark btn-xs" type="button" id="selectBranch" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
										{{ session('branch') =='' ? _lang('All Branch') : session('branch') }}
									</a>
									<div class="dropdown-menu dropdown-menu-right" aria-labelledby="selectBranch">
										<a class="dropdown-item branch-switch" href="{{ route('switch_branch_reset') }}">{{ _lang('All Branch') }}</a>
										<a class="dropdown-item branch-switch" href="#" data-branch-id="default" data-branch-name="{{ get_option('default_branch_name', 'Main Branch') }}">{{ get_option('default_branch_name', 'Main Branch') }}</a>
										@foreach( \App\Models\Branch::all() as $branch )
										<a class="dropdown-item branch-switch" href="#" data-branch-id="{{ $branch->id }}" data-branch-name="{{ $branch->name }}">{{ $branch->name }}</a>
										@endforeach
									</div>
								</div>
								@endif
							</div>
						</div>
					</div>
				</div><!-- page title area end -->
				@endif

				<script>
				$(document).ready(function() {
					$('.branch-switch').click(function(e) {
						e.preventDefault();
						
						var branchId = $(this).data('branch-id');
						var branchName = $(this).data('branch-name');
						
						if (branchId === 'default') {
							// Reset to all branches
							window.location.href = $(this).attr('href');
							return;
						}
						
						// Switch to specific branch
						$.ajax({
							url: '{{ route("switch_branch") }}',
							method: 'POST',
							data: {
								branch_id: branchId,
								_token: '{{ csrf_token() }}'
							},
							success: function(response) {
								if (response.result === 'success') {
									location.reload();
								} else {
									alert(response.message || 'Error switching branch');
								}
							},
							error: function() {
								alert('Error switching branch');
							}
						});
					});
				});
				</script>

				<div class="main-content-inner mt-4">
					<div class="row">
						<div class="{{ isset($alert_col) ? $alert_col : 'col-lg-12' }}">

							@if(auth()->check() && auth()->user()->user_type == 'admin' && auth()->user()->tenant_owner == 1 && request()->tenant->membership_type == 'trial')
							<div class="alert alert-warning alert-dismissible" role="alert">
								<button type="button" class="close" data-dismiss="alert" aria-label="Close">
									<span aria-hidden="true"><i class="far fa-times-circle"></i></span>
								</button>
								<span><i class="fas fa-info-circle mr-2"></i>{{ _lang('Your trial period will end on').' '.request()->tenant->valid_to }}</span>
							</div>
							@endif

							<div class="alert alert-success alert-dismissible" id="main_alert" role="alert">
								<button type="button" id="close_alert" class="close">
									<span aria-hidden="true"><i class="far fa-times-circle"></i></span>
								</button>
								<span class="msg"></span>
							</div>
						</div>
					</div>

					@yield('content')
				</div><!--End main content Inner-->

			</div><!--End main content-->

		</div><!--End Page Container-->

        <!-- jQuery  -->
		<script src="{{ asset('backend/assets/js/vendor/jquery-3.7.1.min.js') }}"></script>
		
		<!-- JavaScript Variables -->
		@include('layouts.others.languages')
		<script src="{{ asset('backend/assets/js/popper.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/metisMenu/metisMenu.min.js') }}"></script>
		<script src="{{ asset('backend/assets/js/print.js') }}"></script>
		<script src="{{ asset('backend/plugins/pace/pace.min.js') }}"></script>
        <script src="{{ asset('backend/plugins/moment/moment.js') }}"></script>

		<!-- Conditional JS -->
        @include('layouts.others.import-js')

		<script src="{{ asset('backend/plugins/dropify/js/dropify.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/sweet-alert2/js/sweetalert2.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/select2/js/select2.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/parsleyjs/parsley.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/jquery-toast-plugin/jquery.toast.min.js') }}"></script>
		<script src="{{ asset('backend/plugins/daterangepicker/daterangepicker.js') }}"></script>
		<script src="{{ asset('backend/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>

        <!-- App js -->
        <script src="{{ asset('backend/assets/js/scripts.js'). '?v=' . filemtime(public_path('backend/assets/js/scripts.js')) }}"></script>
        
        <!-- Chart.js for Security Dashboard -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        @if(get_tenant_option('pwa_enabled', 1))
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

            // PWA Install Prompt for Members
            @if(auth()->check() && auth()->user()->user_type == 'customer' && !isset($_COOKIE['pwa_dismissed']))
            let deferredPrompt;
            
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // Show install prompt after 3 seconds
                setTimeout(() => {
                    showPWAInstallPrompt();
                }, 3000);
            });
            
            function showPWAInstallPrompt() {
                // Check if already installed
                if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                    return;
                }
                
                $.toast({
                    heading: '{{ _lang("Install App") }}',
                    text: '{{ _lang("Get quick access to your account with our mobile app") }}',
                    position: 'top-center',
                    loaderBg: '#007bff',
                    icon: 'info',
                    hideAfter: 8000,
                    stack: 6,
                    showHideTransition: 'slide',
                    afterShown: function() {
                        // Add install button to toast
                        setTimeout(() => {
                            $('.jq-toast-single').append(`
                                <div style="margin-top: 10px;">
                                    <button id="install-pwa-btn" class="btn btn-primary btn-sm" style="margin-right: 5px;">
                                        <i class="fas fa-download"></i> {{ _lang("Install") }}
                                    </button>
                                    <button id="dismiss-pwa-btn" class="btn btn-secondary btn-sm">
                                        {{ _lang("Later") }}
                                    </button>
                                </div>
                            `);
                            
                            $('#install-pwa-btn').on('click', async function() {
                                if (deferredPrompt) {
                                    deferredPrompt.prompt();
                                    const { outcome } = await deferredPrompt.userChoice;
                                    
                                    if (outcome === 'accepted') {
                                        $.toast({
                                            heading: '{{ _lang("Success") }}',
                                            text: '{{ _lang("App installation started!") }}',
                                            position: 'top-right',
                                            loaderBg: '#28a745',
                                            icon: 'success',
                                            hideAfter: 3000,
                                            stack: 6
                                        });
                                    }
                                    
                                    deferredPrompt = null;
                                } else {
                                    window.location.href = '{{ route("pwa.install-prompt") }}';
                                }
                                $('.jq-toast-single').remove();
                            });
                            
                            $('#dismiss-pwa-btn').on('click', function() {
                                document.cookie = "pwa_dismissed=true; expires=" + new Date(Date.now() + 7*24*60*60*1000).toUTCString() + "; path=/";
                                $('.jq-toast-single').remove();
                            });
                        }, 500);
                    }
                });
            }
            
            // Listen for app installed event
            window.addEventListener('appinstalled', () => {
                $.toast({
                    heading: '{{ _lang("App Installed") }}',
                    text: '{{ _lang("App installed successfully! You can now access it from your home screen.") }}',
                    position: 'top-right',
                    loaderBg: '#28a745',
                    icon: 'success',
                    hideAfter: 5000,
                    stack: 6
                });
            });
            @endif
        </script>
        @endif

		@include('layouts.others.alert')

		<!-- PWA Install Prompt -->
		@include('components.pwa-install-prompt')

		<!-- Custom JS -->
		@yield('js-script')
    </body>
</html>
