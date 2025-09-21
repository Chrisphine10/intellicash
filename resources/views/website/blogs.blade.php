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
                        <li class="list-inline-item">/ &nbsp; <a href="{{ url('/features') }}">{{ _lang('Blogs') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Blog preview section-->
<section id="blogs">
    <div class="container my-3">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header">
                    <h3 class="wow animate__zoomIn">{{ _lang('Blogs') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->blogs_heading) ? $pageData->blogs_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->blogs_sub_heading) ? $pageData->blogs_sub_heading : '' }}</p>
                </div>
            </div>
        </div>
        <div class="row gx-4">
            @foreach($blog_posts as $post)
            <div class="col-lg-4 mb-5">
                <div class="latest-post h-100 wow animate__zoomIn" data-wow-delay=".2s">
                    <img class="card-img-top" src="{{ asset('public/uploads/media/'.$post->image) }}" alt="{{ $post->translation->title }}" />
                    <div class="post-body p-4">
                        <p class="post-date">{{ $post->created_at }}</p>
                        <a class="text-decoration-none" href="{{ url('/blogs/'.$post->slug) }}">
                            <h4 class="post-title mb-3">{{ $post->translation->title }}</h4>
                        </a>
                        <a href="{{ url('/blogs/'.$post->slug) }}" class="read-more">{{ _lang('Read More') }} <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination-->
        <nav>
            {{ $blog_posts->links('vendor.pagination.website') }}
        </nav>
        <!-- End Pagination-->
    </div>
</section>
@endsection