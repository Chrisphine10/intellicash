@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('admin.pages.default_pages.store') }}" enctype="multipart/form-data">
	<div class="row">
		<div class="col-lg-8 offset-lg-2">
			<div class="card">
				<div class="card-header d-flex align-items-center justify-content-between">
					<span class="panel-title">{{ _lang('Update About Page') }}</span>
					<a href="{{ route('admin.pages.default_pages') }}" class="btn btn-outline-primary btn-xs"><i class="fas fa-chevron-left mr-2"></i>{{ _lang('Back') }}</a>
				</div>
				<div class="card-body">
					@csrf
					<div class="row">
						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Title') }}</label>
						        <input type="text" class="form-control" name="about_page[title]" value="{{ isset($pageData->title) ? $pageData->title : '' }}" required>
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
						        <label class="control-label">{{ _lang('Section 1 Heading') }}</label>
						        <input type="text" class="form-control" name="about_page[section_1_heading]" value="{{ isset($pageData->section_1_heading) ? $pageData->section_1_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Section 1 Content') }}</label>
						        <textarea class="form-control summernote" name="about_page[section_1_content]">{{ isset($pageData->section_1_content) ? $pageData->section_1_content : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('About Image') }}</label>
						        <input type="file" class="dropify" name="about_page_media[about_image]" data-default-file="{{ isset($pageMedia->about_image) ? asset('public/uploads/media/'.$pageMedia->about_image) : '' }}">
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Section 2 Heading') }}</label>
						        <input type="text" class="form-control" name="about_page[section_2_heading]" value="{{ isset($pageData->section_2_heading) ? $pageData->section_2_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Section 2 Content') }}</label>
						        <textarea class="form-control summernote" name="about_page[section_2_content]">{{ isset($pageData->section_2_content) ? $pageData->section_2_content : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Section 3 Heading') }}</label>
						        <input type="text" class="form-control" name="about_page[section_3_heading]" value="{{ isset($pageData->section_3_heading) ? $pageData->section_3_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Section 3 Content') }}</label>
						        <textarea class="form-control summernote" name="about_page[section_3_content]">{{ isset($pageData->section_3_content) ? $pageData->section_3_content : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Team Heading') }}</label>
						        <input type="text" class="form-control" name="about_page[team_heading]" value="{{ isset($pageData->team_heading) ? $pageData->team_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Team Sub Heading') }}</label>
						        <input type="text" class="form-control" name="about_page[team_sub_heading]" value="{{ isset($pageData->team_sub_heading) ? $pageData->team_sub_heading : '' }}">
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


