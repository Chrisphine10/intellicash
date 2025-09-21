@extends('website.layouts')

@section('content')
<!-- Features section-->
@if(isset($pageData->features_status) && $pageData->features_status == 1)
<section id="services" class="bg-light">
    <div class="container my-3 px-4">
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
@endif

@if(isset($pageData->pricing_status) && $pageData->pricing_status == 1)
<!--Pricing Table-->
<section id="pricing-table">
    <div class="container my-3 px-4">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header">
                    <h3 class="wow animate__zoomIn">{{ _lang('Pricing') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->pricing_heading) ? $pageData->pricing_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->pricing_sub_heading) ? $pageData->pricing_sub_heading : '' }}</p>
                </div>
            </div>
        </div>

        <div class="row gx-5 justify-content-center">

            <div class="d-flex align-items-center justify-content-center">
                @if($packages->where('package_type', 'monthly')->count() > 0)
                <div class="form-check form-switch custom-switch mb-5 me-3">
                    <input class="form-check-input plan_type" type="checkbox" value="monthly" name="plan_type" id="monthy-plans" checked>
                    <label class="form-check-label ms-1 text-primary" for="monthy-plans"><b>{{ _lang('Monthly') }}</b></label>
                </div>
                @endif

                @if($packages->where('package_type', 'yearly')->count() > 0)
                <div class="form-check form-switch custom-switch mb-5 me-3">
                    <input class="form-check-input plan_type" type="checkbox" value="yearly" name="plan_type" id="yearly-plans">
                    <label class="form-check-label ms-1 text-primary" for="yearly-plans"><b>{{ _lang('Yearly') }}</b></label>
                </div>
                @endif

                @if($packages->where('package_type', 'lifetime')->count() > 0)
                <div class="form-check form-switch custom-switch mb-5">
                    <input class="form-check-input plan_type" type="checkbox" value="lifetime" name="plan_type" id="lifetime-plans">
                    <label class="form-check-label ms-1 text-primary" for="lifetime-plans"><b>{{ _lang('Lifetime') }}</b></label>
                </div>
                @endif
            </div>

            @foreach($packages as $package)
            <div class="col-lg-4 mb-5 {{ $package->package_type }}-plan">
                <div class="pricing-plan popular h-100 {{ $package->package_type == 'monthly' ? 'wow' : '' }} animate__zoomIn" data-wow-delay=".6s">
                    <div class="pricing-plan-header">
                        @if($package->is_popular == 1)
                        <span>{{ _lang('Most popular') }}</span>
                        @endif
                        <h5>{{ $package->name }}</h5>
                        @if($package->discount > 0)
                        <p class="d-inline-block">
                            <small><del>{{ decimalPlace($package->cost, currency_symbol()) }}</del></small>
                            <span class="bg-primary d-inline-block text-white px-3 py-1 rounded-pill ms-1">{{ $package->discount.'% '._lang('Discount') }}</span>
                        </p>
                        <h4><span>{{ decimalPlace($package->cost - ($package->discount / 100) * $package->cost, currency_symbol()) }}</span> / {{ ucwords($package->package_type) }}</h4>
                        @else
                        <h4><span>{{ decimalPlace($package->cost, currency_symbol()) }}</span> / {{ ucwords($package->package_type) }}</h4>
                        @endif

                        @if($package->trial_days > 0)
                        <h6 class="mt-2 text-danger">{{ $package->trial_days.' '._lang('Days Free Trial') }}</h6>
                        @else
                        <h6 class="mt-2 text-dark">{{ _lang('No Trial Available') }}</h6>
                        @endif
                    </div>
                    <div class="pricing-plan-body">
                        <ul>
                            <li><i class="bi bi-check2-circle me-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->user_limit).' '._lang('Role Based User') }}</li>
                            <li><i class="bi bi-check2-circle me-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->member_limit).' '._lang('Members') }}</li>
                            <li><i class="bi bi-check2-circle me-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->branch_limit).' '._lang('Additional Branch') }}</li>
                            <li><i class="bi bi-check2-circle me-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->account_type_limit).' '._lang('Account Type') }}</li>
                            <li><i class="bi bi-check2-circle me-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->account_limit).' '._lang('Account') }}</li>
                            <li><i class="bi {{ $package->member_portal == 0 ? 'bi-x-circle' : 'bi-check2-circle' }} me-2"></i>{{ _lang('Member Portal') }}</li>
                        </ul>
                        <a href="{{ route('register') }}?package_id={{ $package->id }}">{{ _lang('Select') }} <i class="bi bi-arrow-right ms-2"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
<!--End Pricing Table-->
@endif

@if(isset($pageData->blog_status) && $pageData->blog_status == 1)
<!-- Blog preview section-->
<section id="blogs" class="bg-light">
    <div class="container my-3 px-4">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header">
                    <h3 class="wow animate__zoomIn">{{ _lang('Blogs') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->blog_heading) ? $pageData->blog_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->blog_sub_heading) ? $pageData->blog_sub_heading : '' }}</p>
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
    </div>
</section>
@endif


@if(isset($pageData->testimonials_status) && $pageData->testimonials_status == 1)
<section id="testimonial">
    <div class="container my-3 px-4">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header mb-5">
                    <h3 class="wow animate__zoomIn">{{ _lang('Testimonials') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->testimonials_heading) ? $pageData->testimonials_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->testimonials_sub_heading) ? $pageData->testimonials_sub_heading : '' }}</p>
                </div>
            </div>
        </div>

        <div class="testimonial-slider">
            @foreach($testimonials as $testimonial)
            <div class="card single-testimonial h-100 mt-5">
                <div class="card-body d-flex align-items-center flex-column justify-content-center text-center">
                    <picture class="avatar">
                        <img class="img-fluid rounded-circle" src="{{ asset('public/uploads/media/'.$testimonial->image) }}" alt="{{ $testimonial->translation->name }}">
                    </picture>

                    <div class="px-4">
                        <p class="lead fw-bolder mb-4 mt-4 text-dark">{{ $testimonial->translation->name }}</p>

                        <p class="font-weight-normal mb-4"><i>"{{ $testimonial->translation->testimonial }}"</i></p>

                        <span class="ratings">
                            <i class="bi bi-star-fill text-primary"></i>
                            <i class="bi bi-star-fill text-primary"></i>
                            <i class="bi bi-star-fill text-primary"></i>
                            <i class="bi bi-star-fill text-primary"></i>
                            <i class="bi bi-star-fill text-primary"></i>
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif


@if(isset($pageData->newsletter_status) && $pageData->newsletter_status == 1)
<!-- Call to action-->
<section id="newsletter" style="background-image: url({{ isset($pageMedia->newsletter_bg_image) ? 'public/uploads/media/'.$pageMedia->newsletter_bg_image : 'public/website/assets/call-to-action.jpg' }})">
    <div class="container px-4">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header mb-5">
                    <h3 class="wow animate__zoomIn">{{ _lang('Newsletter') }}</h3>
                    <h2 class="text-white wow animate__fadeInUp">{{ isset($pageData->newsletter_heading) ? $pageData->newsletter_heading : '' }}</h2>
                    <p class="text-white wow animate__fadeInUp">{{ isset($pageData->newsletter_sub_heading) ? $pageData->newsletter_sub_heading : '' }}</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center wow animate__zoomIn" data-wow-duration="1s">
            <div class="col-lg-6">
                <form action="{{ url('/email_subscription') }}" id="email_subscription" method="post">
                    @csrf
                    <div class="input-group">
                        <input class="form-control" type="text" name="email_address" placeholder="{{ _lang('Email address') }}" aria-label="{{ _lang('Email address') }}" aria-describedby="button-newsletter" required/>
                        <button class="btn btn-primary border-radius-10 px-3" id="button-newsletter" type="submit">{{ _lang('Subscribe') }} <i class="bi bi-arrow-right ms-2"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endif
@endsection
