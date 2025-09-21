@extends('layouts.guest')

@section('content')
<div id="pricing-table">
    <div class="row gx-5 justify-content-center"> 
        <div class="col-lg-12 d-flex justify-content-center">
            <div class="form-check form-switch custom-switch mb-5 me-3">
                <input class="form-check-input plan_type" type="radio" value="monthly" name="plan_type" id="monthy-plans" checked>
                <label class="form-check-label ms-1 text-primary" for="monthy-plans"><b>{{ _lang('Monthly') }}</b></label>
            </div>

            <div class="form-check form-switch custom-switch mb-5 me-3">
                <input class="form-check-input plan_type" type="radio" value="yearly" name="plan_type" id="yearly-plans">
                <label class="form-check-label ms-1 text-primary" for="yearly-plans"><b>{{ _lang('Yearly') }}</b></label>
            </div>

            <div class="form-check form-switch custom-switch mb-5">
                <input class="form-check-input plan_type" type="radio" value="lifetime" name="plan_type" id="lifetime-plans">
                <label class="form-check-label ms-1 text-primary" for="lifetime-plans"><b>{{ _lang('Lifetime') }}</b></label>
            </div>
        </div>       

        @foreach($packages as $package)
        <div class="col-lg-4 mb-5 {{ $package->package_type }}-plan">
            <div class="pricing-plan popular h-100">
                <div class="pricing-plan-header">
                    @if($package->is_popular == 1)
                    <span>{{ _lang('Most popular') }}</span>
                    @endif
                    <h5>{{ $package->name }}</h5>
                    @if($package->discount > 0)
                    <p class="d-inline-block mb-4">
                        <small><del>{{ decimalPlace($package->cost, currency_symbol()) }}</del></small>
                        <span class="bg-info d-inline-block text-white px-3 py-1 rounded-pill ms-1">{{ $package->discount.'% '._lang('Discount') }}</span>
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
                        <li><i class="fas fa-check-circle text-success mr-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->user_limit).' '._lang('Role Based User') }}</li>
                        <li><i class="fas fa-check-circle text-success mr-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->member_limit).' '._lang('Member') }}</li>
                        <li><i class="fas fa-check-circle text-success mr-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->branch_limit).' '._lang('Additional Branch') }}</li>
                        <li><i class="fas fa-check-circle text-success mr-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->account_type_limit).' '._lang('Account Type') }}</li>
                        <li><i class="fas fa-check-circle text-success mr-2"></i>{{ str_replace('-1',_lang('Unlimited'), $package->account_limit).' '._lang('Account') }}</li>
                        <li><i class="{{ $package->member_portal == 0 ? 'fas fa-times-circle text-danger' : 'fas fa-check-circle text-success' }} mr-2"></i>{{ _lang('Member Portal') }}</li>
                    </ul>
                    <form action="{{ route('membership.choose_package') }}" method="post">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ $package->id }}">
                        <button type="submit" class="btn btn-primary btn-block mt-4">{{ _lang('Select Package') }}</button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection