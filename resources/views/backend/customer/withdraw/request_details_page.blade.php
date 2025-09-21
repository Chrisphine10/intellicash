@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        <div class="card">
            <div class="card-header d-sm-flex align-items-center justify-content-between">
                <span class="panel-title">{{ _lang('Withdrawal Request Details') }}</span>
                <div>
                    <a class="btn btn-secondary btn-xs" href="{{ route('withdraw.requests') }}">
                        <i class="fas fa-arrow-left mr-1"></i>{{ _lang('Back to Requests') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('backend.customer.withdraw.request_details')
            </div>
        </div>
    </div>
</div>
@endsection
