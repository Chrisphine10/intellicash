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
                        <li class="list-inline-item">/ &nbsp; <a href="{{ url('/privacy-policy') }}">{{ $page_title }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Page Content-->
<section class="page">
    <div class="container">
        <div class="row gx-5">
            <div class="col-lg-12">
                {!! isset($pageData->content) ? xss_clean($pageData->content) : '' !!}
            </div>
        </div>
    </div>
</section>
@endsection
