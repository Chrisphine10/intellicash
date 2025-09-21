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
                        <li class="list-inline-item">/ &nbsp; <a href="{{ url('/features') }}">{{ _lang('FAQ') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Page Content-->
<section id="faq">
    <div class="container my-3">
        <div class="row gx-5 justify-content-center">
            <div class="col-lg-8 col-xl-6">
                <div class="text-center section-header">
                    <h3 class="wow animate__zoomIn">{{ _lang('FAQ') }}</h3>
                    <h2 class="wow animate__fadeInUp">{{ isset($pageData->faq_heading) ? $pageData->faq_heading : '' }}</h2>
                    <p class="wow animate__fadeInUp">{{ isset($pageData->faq_sub_heading) ? $pageData->faq_sub_heading : '' }}</p>
                </div>
            </div>
        </div>

        <div class="row gx-5">
            <div class="col-xl-12">
                <div class="accordion mb-5" id="faqAccordion">
                    @foreach($faqs as $faq)
                    <div class="faq-item">
                        <h3 class="accordion-header" id="heading{{ $faq->id }}"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $faq->id }}" aria-expanded="false" aria-controls="collapse{{ $faq->id }}"><i class="bi bi-question-circle me-2"></i>{{ $faq->translation->question }}</button></h3>
                        <div class="accordion-collapse collapse" id="collapse{{ $faq->id }}" aria-labelledby="heading{{ $faq->id }}" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                {{ $faq->translation->answer }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
</main>
@endsection
