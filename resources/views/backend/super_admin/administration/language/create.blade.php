@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Create New Language') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('admin.languages.store') }}" enctype="multipart/form-data">
					@csrf
					<div class="row mb-4">
						<label class="col-sm-3 col-form-label form-label">{{ _lang('Language Name') }}</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" name="language_name" value="{{ old('language_name') }}" required>
						</div>
					</div>

					<div class="row mb-4">
						<label class="col-sm-3 col-form-label form-label">{{ _lang('Country') }}</label>
						<div class="col-sm-9">
							@include('backend.super_admin.administration.language.flag')
						</div>
					</div>

					<div class="form-group row">
						<div class="col-xl-9 offset-xl-3">
							<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Submit') }}</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection


