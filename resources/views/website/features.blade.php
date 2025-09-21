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
                        <li class="list-inline-item">/ &nbsp; <a href="{{ url('/features') }}">{{ _lang('Features') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Page Content-->
<section id="services">
    <div class="container my-3">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header">
                    <h3 class="wow animate__zoomIn">{{ _lang('Features') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->features_heading) ? $pageData->features_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->features_sub_heading) ? $pageData->features_sub_heading : '' }}</p>
                </div>
            </div>
        </div>
        
        <div class="row align-items-stretch">                                                     
            @foreach($features as $feature)                                             
            <div class="col-lg-4 mb-5 d-flex">
                <div class="feature wow animate__zoomIn flex-fill" data-wow-delay=".2s">
                    <div class="icon text-primary fw-bold mb-4">
                        {!! xss_clean($feature->icon) !!}
                    </div>
                    <h2 class="mb-1 mb-3">{{ $feature->translation->title }}</h2>
                    <p>{{ $feature->translation->content }}</p>
                </div>
            </div>
            @endforeach            
        </div>
    </div>
</section>
@endsection
