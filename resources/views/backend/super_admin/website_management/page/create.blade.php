@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('admin.pages.store') }}" enctype="multipart/form-data">

	<div class="row">
		<div class="col-lg-8 offset-lg-2">
			<div class="card">
				<div class="card-header">
					<span class="panel-title">{{ _lang('Add New Page') }}</span>
				</div>
				<div class="card-body">
					@csrf
					<div class="row">
						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Title') }}</label>
						        <input type="text" class="form-control" name="trans[title]" value="{{ old('trans.title') }}" required>
					        </div>
					    </div>

					    <div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Body') }}</label>
						        <textarea class="form-control summernote" name="trans[body]">{{ old('trans.body') }}</textarea>
					        </div>
					    </div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('status','1') }}" name="status" required>
									<option value="1">{{ _lang('Publish') }}</option>
									<option value="0">{{ _lang('Draft') }}</option>
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


