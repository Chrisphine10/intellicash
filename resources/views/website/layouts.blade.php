<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>{{ !isset($page_title) ? get_option('site_title', config('app.name')) : $page_title }}</title>
        <!-- Favicon-->
        <link rel="icon" type="image/png" href="{{ get_favicon() }}" />

        <!-- Bootstrap icons-->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css" rel="stylesheet">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700;800&display=swap" rel="stylesheet">

        <!-- Core theme CSS (includes Bootstrap)-->
        <link href="{{ asset('public/website/css/animate.css') }}" rel="stylesheet">
        <link href="{{ asset('public/website/vendors/slick/slick.css') }}" rel="stylesheet" />
        <link href="{{ asset('public/website/vendors/slick/slick-theme.css') }}" rel="stylesheet" />
        <link href="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.css') }}" rel="stylesheet" />
        <link href="{{ asset('public/website/css/styles.css') . '?v=' . filemtime(public_path('website/css/styles.css')) }}" rel="stylesheet" />
        @php $header_footer_settings = json_decode(get_trans_option('header_footer_page')); @endphp
        @php $header_footer_media = json_decode(get_trans_option('header_footer_page_media')); @endphp

        @include('website.custom-css')
    </head>
    <body class="d-flex flex-column h-100">
        <!--Preloader-->
        <div id="preloader">
            <div class="loader"></div>
        </div>

        <main class="flex-shrink-0">
            <header class="header-area position-relative">
                <!-- Navigation-->
                <nav class="navbar navbar-expand-lg fkr-navbar" id="main_navbar">
                    <div class="container">
                        <a class="navbar-brand" href="{{ url('/') }}"><img src="{{ get_logo() }}" alt="logo"/></a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <div class="collapse navbar-collapse" id="navbarSupportedContent">
                            <ul class="navbar-nav ms-auto d-flex">
                                <li class="nav-item"><a class="nav-link {{ url()->current() == url('/') ? 'active' : '' }}" href="{{ url('/') }}">{{ _lang('Home') }}</a></li>
                                <li class="nav-item"><a class="nav-link {{ url()->current() == url('/about') ? 'active' : '' }}" href="{{ url('/about') }}">{{ _lang('About') }}</a></li>
                                <li class="nav-item"><a class="nav-link {{ url()->current() == url('/features') ? 'active' : '' }}" href="{{ url('/features') }}">{{ _lang('Features') }}</a></li> 
                                <li class="nav-item"><a class="nav-link {{ url()->current() == url('/pricing') ? 'active' : '' }}" href="{{ url('/pricing') }}">{{ _lang('Pricing') }}</a></li> 

                                @php $otherPages = \App\Models\Page::active()->get(); @endphp
                                <li class="nav-item">
                                    <a class="nav-link has-submenu" href="#">{{ _lang('Resources') }}</a>
                                    <ul class="submenu">
                                        <li class="nav-item"><a class="nav-link {{ url()->current() == url('/blogs') ? 'active' : '' }}" href="{{ url('/blogs') }}">{{ _lang('Blogs') }}</a></li>
                                        <li class="nav-item"><a class="nav-link {{ url()->current() == url('/faq') ? 'active' : '' }}" href="{{ url('/faq') }}">{{ _lang('FAQ') }}</a></li>
                                        @foreach($otherPages as $d_page)
                                        <li class="nav-item"><a class="nav-link" href="{{ url('/'.$d_page->slug) }}">{{ $d_page->translation->title }}</a></li>
                                        @endforeach
                                    </ul>
                                </li>  
                                
                                <li class="nav-item"><a class="nav-link {{ url()->current() == url('/contact') ? 'active' : '' }}" href="{{ url('/contact') }}">{{ _lang('Contact') }}</a></li>
                            </ul>

                            <ul class="navbar-nav ms-auto d-flex">
                                @auth
                                    <li class="nav-item"><a class="nav-link me-2 btn-register py-2 text-nowrap" href="{{ route('login') }}"><i class="bi bi-speedometer2 me-2 d-none d-lg-inline"></i>{{ _lang('Dashboard') }}</a></li>
                                    <li class="nav-item"><a class="nav-link btn-login py-2 text-nowrap" href="{{ url('/logout') }}"><i class="bi bi-box-arrow-left me-2 d-none d-lg-inline"></i>{{ _lang('Logout') }}</a></li>
                                @endauth

                                @guest
                                    <li class="nav-item"><a class="nav-link me-2 btn-login py-2 text-nowrap" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2 d-none d-lg-inline"></i>{{ _lang('Sign In') }}</a></li>
                                    <li class="nav-item"><a class="nav-link btn-register py-2 text-nowrap" href="{{ route('register') }}"><i class="bi bi-person-plus me-2 d-none d-lg-inline"></i>{{ _lang('Sign Up') }}</a></li>                        
                                @endguest
                            </ul>
                        </div>
                    </div>
                </nav>

                @if(request()->is('/'))
                <!-- Header-->
                <div class="hero-area" style="background-image: url({{ isset($pageMedia->hero_image) ? asset('public/uploads/media/'.$pageMedia->hero_image) : asset('public/website/assets/hero-bg.jpg') }})">
                    <div class="container px-4">
                        <div class="row gx-5 align-items-center justify-content-center">
                            <div class="col-lg-8">
                                <div class="my-5 text-center hero-content">
                                    <h1 class="wow animate__fadeInUp" data-wow-delay="0.4s">{!! isset($pageData->hero_heading) ? xss_clean($pageData->hero_heading) : '' !!}</h1>
                                    <p class="wow animate__fadeInUp" data-wow-delay="0.6s">{!! isset($pageData->hero_sub_heading) ? xss_clean($pageData->hero_sub_heading) : '' !!}</p>

                                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                                        <form action="{{ url('/pricing') }}" class="wow animate__fadeInUp" autocomplete="off" data-wow-delay="1s" method="get">
                                            <div class="form-control py-0 pr-0 d-flex align-items-center parent-box">
                                                <span class="text-nowrap">{{ parse_url(url(''), PHP_URL_HOST).'/' }}</span>
                                                <input type="text" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}" name="workspace" value="{{ old('workspace') }}" placeholder="{{ _lang('workspace') }}" required>
                                                <div id="icon-box" class="me-2"></div>
                                                <button type="submit" class="btn btn-primary d-none d-sm-inline-block py-3 px-4 text-nowrap fw-bold border-2 shadow border-right-radius-10" id="get-started-btn">{{ _lang('Get Started') }} <i class="bi bi-arrow-right ms-2"></i></button>
                                            </div>
                                            <div class="d-grid gap-2 d-block d-sm-none mt-2">
                                                <button type="submit" class="btn btn-primary py-3 px-4 text-nowrap fw-bold border-2 shadow border-radius-10" id="get-started-btn">{{ _lang('Get Started') }} <i class="bi bi-arrow-right ms-2"></i></button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </header>

            @yield('content')

            @php $gdpr_cookie_consent = json_decode(get_trans_option('gdpr_cookie_consent_page')) @endphp
            
            @if(isset($gdpr_cookie_consent->cookie_consent_status) && $gdpr_cookie_consent->cookie_consent_status == 1)
            @include('cookie-consent::index')
            @endif
        </main>
        
        <!-- Footer-->
        <footer class="footer">
            <!-- Footer Top -->
            <div class="footer-top">
                <div class="container px-4">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 col-12">
                            <!-- Single Widget -->
                            <div class="single-footer about">
                                <div class="logo">
                                    <a href="#"><h4>{{ isset($header_footer_settings->widget_1_heading) ? $header_footer_settings->widget_1_heading : '' }}</h4></a>
                                </div>

                                <p class="text">{{ isset($header_footer_settings->widget_1_content) ? $header_footer_settings->widget_1_content : '' }}</p>
                                
                            </div>
                            <!-- End Single Widget -->
                        </div>
                        <div class="col-lg-3 col-md-6 col-12">
                            <!-- Single Widget -->
                            <div class="single-footer links">
                                <h4>{{ isset($header_footer_settings->widget_2_heading) ? $header_footer_settings->widget_2_heading : '' }}</h4>
                                <ul>
                                    @if(isset($header_footer_settings->widget_2_menus))
                                    @foreach($header_footer_settings->widget_2_menus as $widget_2_menu)
                                        <li><a href="{{ url('/'.$widget_2_menu) }}">{{ get_page_title($widget_2_menu) }}</a></li>
                                    @endforeach
                                    @endif
                                </ul>
                            </div>
                            <!-- End Single Widget -->
                        </div>
                        <div class="col-lg-3 col-md-6 col-12">
                            <!-- Single Widget -->
                            <div class="single-footer links">
                                <h4>{{ isset($header_footer_settings->widget_3_heading) ? $header_footer_settings->widget_3_heading : '' }}</h4>
                                <ul>
                                    @if(isset($header_footer_settings->widget_3_menus))
                                    @foreach($header_footer_settings->widget_3_menus as $widget_3_menu)
                                        <li><a href="{{ url('/'.$widget_3_menu) }}">{{ get_page_title($widget_3_menu) }}</a></li>
                                    @endforeach
                                    @endif
                                </ul>
                            </div>
                            <!-- End Single Widget -->
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Footer Top -->
            <div class="copyright">
                <div class="container px-4">
                    <div class="inner">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="left">
                                    {!! isset($header_footer_settings->copyright_text) ? xss_clean($header_footer_settings->copyright_text) : '' !!}
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <div class="right">
                                    <img src="{{ isset($header_footer_media->payment_gateway_image) ? asset('public/uploads/media/'.$header_footer_media->payment_gateway_image) : asset('public/website/assets/payment_gateways.png') }}" alt="#">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        
        <script src="{{ asset('public/website/js/jquery-3.7.1.min.js') }}"></script>
        <script src="{{ asset('public/website/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('public/website/vendors/slick/slick.min.js') }}"></script>
        <script src="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.js') }}"></script>
        <script src="{{ asset('public/website/js/wow.min.js') }}"></script>

        <!-- Core theme JS-->
        <script src="{{ asset('public/website/js/scripts.js') }}"></script>
        @include('website.custom-js')
    </body>
</html>
