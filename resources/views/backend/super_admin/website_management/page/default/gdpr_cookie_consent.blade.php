@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('admin.pages.default_pages.store') }}" enctype="multipart/form-data">
	<div class="row">
		<div class="col-lg-8 offset-lg-2">
			<div class="card">
				<div class="card-header d-flex align-items-center justify-content-between">
					<span class="panel-title">{{ _lang('GDPR Cookie Consent') }}</span>
					<a href="{{ route('admin.pages.default_pages') }}" class="btn btn-outline-primary btn-xs"><i class="fas fa-chevron-left mr-2"></i>{{ _lang('Back') }}</a>
				</div>
				<div class="card-body">
					@csrf
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Cookie Consent') }}</label>
								<select class="form-control auto-select" name="gdpr_cookie_consent_page[cookie_consent_status]" data-selected="{{ isset($pageData->cookie_consent_status) ? $pageData->cookie_consent_status : 0 }}" required>
									<option value="0">{{ _lang('Disabled') }}</option>
									<option value="1">{{ _lang('Active') }}</option>
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Language') }}</label>
								<select class="form-control" name="model_language" required>
									{{ load_language(get_language()) }}
								</select>
							</div>
						</div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Cookie Message') }}</label>
						        <textarea class="form-control" name="gdpr_cookie_consent_page[cookie_message]">{{ isset($pageData->cookie_message) ? $pageData->cookie_message : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary  mt-2"><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
							</div>
						</div>
					</div>
				</div>
			</div>
	    </div>
	</div>
</form>
@endsection


