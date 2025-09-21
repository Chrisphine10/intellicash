@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <span class="header-title">{{ _lang('User Details') }}</span>
            </div>

            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <td colspan="2" class="text-center"><img class="thumb-xl rounded" src="{{ profile_picture($user->profile_picture) }}"></td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Name') }}</td>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Email') }}</td>
                        <td>{{ $user->email }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Status') }}</td>
                        <td>{!! xss_clean(user_status($user->status)) !!}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Phone') }}</td>
                        <td>{{ $user->country_code.$user->mobile }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('City') }}</td>
                        <td>{{ $user->city }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('State') }}</td>
                        <td>{{ $user->state }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('ZIP') }}</td>
                        <td>{{ $user->zip }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Address') }}</td>
                        <td>{{ $user->address }}</td>
                    </tr>
                    <tr>
                        <td>{{ _lang('Registered At') }}</td>
                        <td>{{ $user->created_at }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection