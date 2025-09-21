@extends('website.layouts')

@section('content')
<!-- Header-->
<header class="bg-header">
    <div class="container px-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xxl-6">
                <div class="text-center my-5">
                    <h3 class="wow animate__zoomIn">{{ $page_title }}</h3>
                    <ul class="list-inline breadcrumbs text-capitalize">
                        <li class="list-inline-item"><a href="{{ url('/') }}">{{ _lang('Home') }}</a></li>
                        <li class="list-inline-item">/ &nbsp; <a href="{{ url('/about') }}">{{ _lang('About') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- About section one-->
<section class="section" id="about">
    <div class="container px-4">
        <div class="row justify-content-center align-items-center">
            <div class="col-lg-7">
                <div class="section-title">
                    <h2 class="mb-4 wow animate__fadeInUp" data-wow-delay="0.2s">{{ isset($pageData->section_1_heading) ? $pageData->section_1_heading : '' }}</h2>
                    <div class="content pe-0 pe-lg-5 wow animate__fadeInUp" data-wow-delay="0.4s">
                        {!! isset($pageData->section_1_content) ? xss_clean($pageData->section_1_content) : '' !!}
                    </div>
                </div>
            </div>
            <div class="col-lg-5 mt-5 mt-lg-0 wow animate__fadeInRight" data-wow-delay="0.4s">
                <img src="{{ isset($pageMedia->about_image) ? asset('public/uploads/media/'.$pageMedia->about_image) : asset('public/website/assets/about-us.jpg') }}" alt="" class="rounded w-100">
            </div>
        </div>
    </div>
</section>

<section class="about-section section bg-sky">
    <div class="container">
        <div class="row justify-content-around">
            <div class="col-lg-5 wow animate__fadeInLeft">
                <div class="section-title">
                    <p class="text-primary text-uppercase fw-bold mb-3"></p>
                    <h2>{{ isset($pageData->section_2_heading) ? $pageData->section_2_heading : '' }}</h2>
                </div>
                {!! isset($pageData->section_2_content) ? xss_clean($pageData->section_2_content) : '' !!}
            </div>
            <div class="col-lg-5 wow animate__fadeInRight">
                <div class="section-title">
                    <p class="text-primary text-uppercase fw-bold mb-3"></p>
                    <h2>{{ isset($pageData->section_3_heading) ? $pageData->section_3_heading : '' }}</h2>
                </div>
                {!! isset($pageData->section_3_content) ? xss_clean($pageData->section_3_content) : '' !!}
            </div>
        </div>
    </div>
    <div class="has-shapes">
        <svg class="shape shape-left text-light" width="381" height="443" viewBox="0 0 381 443" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M334.266 499.007C330.108 469.108 304.151 446.496 276.261 435.921C248.372 425.346 218.077 424.035 188.666 419.32C159.254 414.589 128.795 405.375 108.664 383.129C72.8533 343.535 83.3445 282.01 77.7634 228.587C69.3017 147.754 15.4873 73.3967 -58.0001 40.9907" stroke="currentColor" stroke-miterlimit="10"></path>
            <path d="M349.584 485.51C345.427 455.611 319.469 433 291.58 422.425C263.69 411.85 233.395 410.538 203.984 405.823C174.573 401.092 144.114 391.878 123.982 369.632C88.1716 330.038 98.6628 268.513 93.0817 215.09C84.62 134.258 30.8056 59.8999 -42.6819 27.494" stroke="currentColor" stroke-miterlimit="10"></path>
            <path d="M364.904 472.013C360.747 442.114 334.789 419.503 306.9 408.928C279.011 398.352 248.716 397.041 219.304 392.326C189.893 387.595 159.434 378.381 139.303 356.135C103.492 316.541 113.983 255.016 108.402 201.593C99.9403 120.76 46.1259 46.4028 -27.3616 13.9969" stroke="currentColor" stroke-miterlimit="10"></path>
            <path d="M380.24 458.516C376.083 428.617 350.125 406.006 322.236 395.431C294.347 384.856 264.051 383.544 234.64 378.829C205.229 374.098 174.77 364.884 154.639 342.638C118.828 303.044 129.319 241.519 123.738 188.096C115.276 107.264 61.4619 32.906 -12.0255 0.500103" stroke="currentColor" stroke-miterlimit="10"></path>
        </svg>
        <svg class="shape shape-right text-light" width="406" height="433" viewBox="0 0 406 433" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M101.974 -86.77C128.962 -74.8992 143.467 -43.2447 146.175 -12.7857C148.883 17.6734 142.273 48.1263 139.087 78.5816C135.916 109.041 136.681 141.702 152.351 167.47C180.247 213.314 240.712 218.81 289.413 238.184C363.095 267.516 418.962 340.253 430.36 421.687" stroke="currentColor" stroke-miterlimit="10"></path>
            <path d="M118.607 -98.5031C145.596 -86.6323 160.101 -54.9778 162.809 -24.5188C165.517 5.94031 158.907 36.3933 155.72 66.8486C152.549 97.3082 153.314 129.969 168.985 155.737C196.881 201.581 257.346 207.077 306.047 226.451C379.729 255.783 435.596 328.52 446.994 409.954" stroke="currentColor" stroke-miterlimit="10"></path>
            <path d="M135.241 -110.238C162.23 -98.3675 176.735 -66.7131 179.443 -36.254C182.151 -5.79492 175.541 24.6581 172.354 55.1134C169.183 85.573 169.948 118.234 185.619 144.002C213.515 189.846 273.98 195.342 322.681 214.716C396.363 244.048 452.23 316.785 463.627 398.219" stroke="currentColor" stroke-miterlimit="10"></path>
            <path d="M151.879 -121.989C178.867 -110.118 193.373 -78.4638 196.081 -48.0047C198.789 -17.5457 192.179 12.9074 188.992 43.3627C185.821 73.8223 186.586 106.483 202.256 132.251C230.153 178.095 290.618 183.591 339.318 202.965C413.001 232.297 468.867 305.034 480.265 386.468" stroke="currentColor" stroke-miterlimit="10"></path>
        </svg>
    </div>
</section>

<!-- Team members section-->
<section class="section teams">
    <div class="container px-4">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header">
                    <h3 class="wow animate__zoomIn">{{ _lang('Teams') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->team_heading) ? $pageData->team_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->team_sub_heading) ? $pageData->team_sub_heading : '' }}</p>
                </div>
            </div>
        </div>
        
        <div class="row gx-5 justify-content-center">
            @foreach($team_members as $team)
            <div class="col-12 col-md-6 col-lg-4 wow animate__zoomIn" data-wow-delay=".2s">
                <div class="card team rounded shadow-lg h-100">
                    <div class="card-body d-flex align-items-center flex-column justify-content-center text-center p-5">
                        <picture class="avatar">
                            <img class="img-fluid rounded-circle" src="{{ asset('public/uploads/media/'.$team->image) }}" alt="">
                        </picture>
                        <p class="lead fw-bolder mb-0 mt-4 text-dark">{{ $team->translation->name }}</p>
                        <p class="text-primary small fw-bold mb-4">{{ $team->translation->role }}</p>
                        <p>{{ $team->translation->description }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>     
@endsection