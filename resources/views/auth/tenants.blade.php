@extends('layouts.auth')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-signin p-2 my-5">
                <div class="card-body">
					<img class="logo" src="{{ get_logo() }}">
					
					<h5 class="text-center py-4">{{ _lang('Select Tenant Account') }}</h4> 
					
                    @if(Session::has('error'))
                        <div class="alert alert-danger text-center">
                            <strong>{{ session('error') }}</strong>
                        </div>
                    @endif
					
					@if(Session::has('success'))
                        <div class="alert alert-success text-center">
                            <strong>{{ session('success') }}</strong>
                        </div>
                    @endif

					<ul class="list-group mb-4">
                        @foreach($users as $user)
                            <li class="list-group-item">
                                <a class="tenant-link d-block d-flex justify-content-between align-items-center" href="{{ route('tenant.login', ['tenant' => $user->tenant->slug, 'email' => $user->email]) }}">     
                                    <span>
                                        <i class="fas fa-globe mr-2"></i>
                                        {{ $user->tenant->name }}
                                    </span>
                                    <i class="fas fa-arrow-alt-circle-right"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection