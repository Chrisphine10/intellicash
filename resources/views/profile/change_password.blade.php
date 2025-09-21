@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-6 offset-lg-3">
		<div class="card">
			<div class="card-header panel-title text-center">
				{{ _lang('Change Password') }}
			</div>

			<div class="card-body">
				@php $isAadminRoute = auth()->user()->user_type == 'superadmin' ? 'admin.' : ''; @endphp
				<form action="{{ route($isAadminRoute.'profile.update_password') }}" class="form-horizontal form-groups-bordered validate" enctype="multipart/form-data" method="post" accept-charset="utf-8">
					@csrf
					<div class="row">
						<div class="col-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Old Password') }}</label>
								<input type="password" class="form-control" name="oldpassword" required>
							</div>
						</div>

						<div class="col-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('New Password') }}</label>
								<input type="password" class="form-control" name="password" required>
							</div>
						</div>

						<div class="col-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Confirm Password') }}</label>
								<input type="password" class="form-control" id="password-confirm" name="password_confirmation" required>
							</div>
						</div>

						<div class="col-12">
							<div class="form-group">
								<button type="submit" class="btn btn-primary btn-block"><i class="ti-check-box mr-2"></i>{{ _lang('Update Password') }}</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection