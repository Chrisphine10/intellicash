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
                        <li class="list-inline-item">/ &nbsp; <a href="{{ url('/pricing') }}">{{ _lang('Pricing') }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Page Content-->
<section id="pricing-table">
    <div class="container my-3">
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
                        <a href="{{ url('register') }}?{{ http_build_query(array_merge(request()->query(), ['package_id' => $package->id])) }}">
                            {{ _lang('Select') }} <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach  
        </div>
    </div>
</section>
@endsection