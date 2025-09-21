@extends('layouts.app')

@section('content')
<div class="row">  
    <div class="col-xl-8 offset-xl-2">
        <div id="pricing-table">
            <div class="row">      
                @if($package != null)
                <div class="col-lg-6">
                    <div class="pricing-plan popular h-100 {{ $package->package_type == 'monthly' ? 'wow' : '' }} animate__zoomIn" data-wow-delay=".6s">
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
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-0 mt-4 mt-lg-0">
                        <div class="card-header text-center">
                            <span class="panel-title">{{ _lang('Membership Details') }}</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tr>
                                    <td>{{ _lang('Membership Type') }}</td>
                                    <td>{{ ucwords(request()->tenant->membership_type) }}</td>
                                </tr>
                                <tr>
                                    <td>{{ _lang('Subscription Date') }}</td>
                                    <td>{{ request()->tenant->subscription_date }}</td>
                                </tr>
                                <tr>
                                    <td>{{ _lang('Subscription Expired') }}</td>
                                    <td>{{ request()->tenant->valid_to }}</td>
                                </tr>
                                <tr>
                                    <td>{{ _lang('Last Payment') }}</td>
                                    <td>{{ $lastPayment ? decimalPlace($lastPayment->amount, currency_symbol()) : _lang('N/A') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ _lang('Last Payment Date') }}</td>
                                    <td>{{ $lastPayment ? $lastPayment->created_at : _lang('N/A') }}</td>
                                </tr>
                            </table>
                            <form action="{{ route('membership.choose_package') }}" method="post">
                                @csrf
                                <input type="hidden" name="package_id" value="{{ $package->id }}">
                                <a href="{{ route('membership.payment_gateways') }}" class="btn btn-primary btn-block mt-4">{{ _lang('Renew Membership') }}</a>
                                <a href="{{ route('membership.packages') }}" class="btn btn-danger btn-block mt-2" id="change-package">{{ _lang('Change Package') }}</a>
                            </form>
                        </div>
                    </div>
                </div>

                @endif
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <span class="panel-title">{{ _lang('Subscription Payments') }}</span>
                    </div>
                    <div class="card-body px-0 pt-0">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th class="pl-4">{{ _lang('Payment Date') }}</th>
                                        <th>{{ _lang('Order ID') }}</th>
                                        <th>{{ _lang('Payment Method') }}</th>
                                        <th>{{ _lang('Amount') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $payment)
                                    <tr>
                                        <td class="pl-4">{{ $payment->created_at }}</td>
                                        <td>{{ $payment->order_id }}</td>
                                        <td>{{ $payment->payment_method }}</td>
                                        <td>{{ decimalPlace($payment->amount, currency_symbol()) }}</td>
                                        <td>
                                            @if($payment->status == 1)
                                            <span class="badge badge-success">{{ _lang('Paid') }}</span>
                                            @else
                                            <span class="badge badge-danger">{{ _lang('Unpaid') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table> 
                        </div>

                        <div class="float-right">
                            {{ $payments->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js-script')
<script>
(function ($) {
    "use strict";

    $(document).on('click','#change-package', function(e){
        e.preventDefault();
        var link = $(this).attr('href');

        Swal.fire({
			text: '{{ _lang('Once you process then you will not able to rollback current subscription. You need to repay for new selected package !') }}',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: '{{ _lang('Yes Process') }}',
			cancelButtonText: $lang_cancel_button_text
		}).then((result) => {
			if (result.value) {
				window.location.href = link;
			}
		});
    });
    
})(jQuery);
</script>
@endsection