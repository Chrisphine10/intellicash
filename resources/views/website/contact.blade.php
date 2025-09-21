@extends('website.layouts')

@section('content')
    <!-- Header-->
    <header class="bg-header">
        <div class="container px-5">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xxl-6">
                    <div class="text-center my-5">
                        <h3 class="wow animate__zoomIn">{{ $page_title }}</h3>
                        <ul class="list-inline breadcrumbs text-capitalize">
                            <li class="list-inline-item"><a href="{{ url('/') }}">{{ _lang('Home') }}</a></li>
                            <li class="list-inline-item">/ &nbsp; <a href="{{ url('/contact') }}">{{ _lang('Contact') }}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!--Contact Us Section-->
    <section class="contact-us">
        <div class="container px-4">
            <div class="contact-head">
                <div class="row">
                    <div class="col-lg-8 mb-lg-0 mb-5 col-12">
                        @if (Session::has('success'))
                            <div class="alert alert-success">
                                <strong>{{ session('success') }}</strong>
                            </div>
                        @endif

                        @if (Session::has('error'))
                            <div class="alert alert-danger">
                                <strong>{{ session('error') }}</strong>
                            </div>
                        @endif

                        <div class="form-main">
                            <div class="title">
                                <h2 class="text-dark fw-bold mb-3">
                                    {{ isset($pageData->contact_form_heading) ? $pageData->contact_form_heading : '' }}</h2>
                                <p>{{ isset($pageData->contact_form_sub_heading) ? $pageData->contact_form_sub_heading : '' }}
                                </p>
                            </div>
                            <form class="form" method="post" autocomplete="off" action="{{ url('/send_message') }}">
                                @csrf
                                <div class="row">
                                    <div class="col-lg-6 col-12">
                                        <div class="form-group">
                                            <input name="name" type="text" placeholder="{{ _lang('Your Name') }}"
                                                class="wow animate__zoomIn" data-wow-delay=".2s" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-12">
                                        <div class="form-group">
                                            <input name="email" type="email" placeholder="{{ _lang('Your Email') }}"
                                                class="wow animate__zoomIn" data-wow-delay=".2s" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 col-12">
                                        <div class="form-group">
                                            <input name="subject" type="text" placeholder="{{ _lang('Your Subjects') }}"
                                                class="wow animate__zoomIn" data-wow-delay=".4s" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <textarea name="message" placeholder="{{ _lang('Your Message') }}" class="wow animate__zoomIn" data-wow-delay=".6s"
                                                required></textarea>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <input type="hidden" name="g-recaptcha-response" id="recaptcha">
                                            @if ($errors->has('g-recaptcha-response'))
                                                <span class="invalid-feedback d-block">
                                                    <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-group">
                                            <button type="submit" class="send-btn wow animate__zoomIn"
                                                data-wow-delay=".8s">{{ _lang('Send Message') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-4 col-12">
                        <div class="single-head">
                            @if (isset($pageData->contact_info_heading))
                                @foreach ($pageData->contact_info_heading as $contact_info_heading)
                                    <div class="single-info">
                                        <h4 class="title text-dark fw-bold">{{ $contact_info_heading }}</h4>
                                        <div class="content">
                                            {!! isset($pageData->contact_info_content[$loop->index])
                                                ? xss_clean($pageData->contact_info_content[$loop->index])
                                                : '' !!}
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            <div class="single-info">
                                <h4 class="title text-dark fw-bold">{{ _lang('Social Links') }}</h4>
                                <div class="content">
                                    <ul class="list-unstyled d-flex">
                                        <li class="me-2 wow animate__zoomIn" data-wow-delay=".2s"><a
                                                href="{{ isset($pageData->facebook_link) ? $pageData->facebook_link : '' }}"
                                                class="text-decoration-none"><i class="bi bi-facebook ri-2x"></i></a></li>
                                        <li class="me-2 wow animate__zoomIn" data-wow-delay=".4s"><a
                                                href="{{ isset($pageData->linkedin_link) ? $pageData->linkedin_link : '' }}"
                                                class="text-decoration-none"><i class="bi bi-linkedin ri-2x"></i></a></li>
                                        <li class="me-2 wow animate__zoomIn" data-wow-delay=".6s"><a
                                                href="{{ isset($pageData->twitter_link) ? $pageData->twitter_link : '' }}"
                                                class="text-decoration-none"><i class="bi bi-twitter ri-2x"></i></a></li>
                                        <li class="me-2 wow animate__zoomIn" data-wow-delay=".8s"><a
                                                href="{{ isset($pageData->youtube_link) ? $pageData->youtube_link : '' }}"
                                                class="text-decoration-none"><i class="bi bi-youtube ri-2x"></i></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @if (get_option('enable_recaptcha', 0) == 1)
        <script src="https://www.google.com/recaptcha/api.js?render={{ get_option('recaptcha_site_key') }}"></script>
        <script>
            grecaptcha.ready(function() {
                grecaptcha.execute('{{ get_option('recaptcha_site_key') }}', {
                    action: 'contact'
                }).then(function(token) {
                    if (token) {
                        document.getElementById('recaptcha').value = token;
                    }
                });
            });
        </script>
    @endif
@endsection
