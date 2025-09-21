@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('admin.pages.default_pages.store') }}" enctype="multipart/form-data">
	<div class="row">
		<div class="col-lg-8 offset-lg-2">
			<div class="card">
				<div class="card-header d-flex align-items-center justify-content-between">
					<span class="panel-title">{{ _lang('Update Home Page') }}</span>
					<a href="{{ route('admin.pages.default_pages') }}" class="btn btn-outline-primary btn-xs"><i class="fas fa-chevron-left mr-2"></i>{{ _lang('Back') }}</a>
				</div>
				<div class="card-body">
					@csrf
					<div class="row">
						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Title') }}</label>
						        <input type="text" class="form-control" name="home_page[title]" value="{{ isset($pageData->title) ? $pageData->title : '' }}" required>
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
						        <label class="control-label">{{ _lang('Hero Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[hero_heading]" value="{{ isset($pageData->hero_heading) ? $pageData->hero_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Hero Sub Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[hero_sub_heading]" value="{{ isset($pageData->hero_sub_heading) ? $pageData->hero_sub_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Hero Image') }}</label>
						        <input type="file" class="dropify" name="home_page_media[hero_image]" data-default-file="{{ isset($pageMedia->hero_image) ? asset('public/uploads/media/'.$pageMedia->hero_image) : '' }}">
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Features Section') }}</label>
						        <select class="form-control auto-select" data-selected="{{ isset($pageData->features_status) ? $pageData->features_status : '' }}" name="home_page[features_status]" required>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('Disabled') }}</option>
								</select>
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Features Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[features_heading]" value="{{ isset($pageData->features_heading) ? $pageData->features_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Features Sub Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[features_sub_heading]" value="{{ isset($pageData->features_sub_heading) ? $pageData->features_sub_heading : '' }}">
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Pricing Section') }}</label>
						        <select class="form-control auto-select" data-selected="{{ isset($pageData->pricing_status) ? $pageData->pricing_status : '' }}" name="home_page[pricing_status]" required>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('Disabled') }}</option>
								</select>
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Pricing Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[pricing_heading]" value="{{ isset($pageData->pricing_heading) ? $pageData->pricing_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Pricing Sub Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[pricing_sub_heading]" value="{{ isset($pageData->pricing_sub_heading) ? $pageData->pricing_sub_heading : '' }}">
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Blog Section') }}</label>
						        <select class="form-control auto-select" data-selected="{{ isset($pageData->blog_status) ? $pageData->blog_status : '' }}" name="home_page[blog_status]" required>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('Disabled') }}</option>
								</select>
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Blog Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[blog_heading]" value="{{ isset($pageData->blog_heading) ? $pageData->blog_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Blog Sub Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[blog_sub_heading]" value="{{ isset($pageData->blog_sub_heading) ? $pageData->blog_sub_heading : '' }}">
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Testimonials Section') }}</label>
						        <select class="form-control auto-select" data-selected="{{ isset($pageData->testimonials_status) ? $pageData->testimonials_status : '' }}" name="home_page[testimonials_status]" required>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('Disabled') }}</option>
								</select>
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Testimonials Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[testimonials_heading]" value="{{ isset($pageData->testimonials_heading) ? $pageData->testimonials_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Testimonials Sub Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[testimonials_sub_heading]" value="{{ isset($pageData->testimonials_sub_heading) ? $pageData->testimonials_sub_heading : '' }}">
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Newsletter Section') }}</label>
						        <select class="form-control auto-select" data-selected="{{ isset($pageData->newsletter_status) ? $pageData->newsletter_status : '' }}" name="home_page[newsletter_status]" required>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('Disabled') }}</option>
								</select>
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Newsletter Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[newsletter_heading]" value="{{ isset($pageData->newsletter_heading) ? $pageData->newsletter_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Newsletter Sub Heading') }}</label>
						        <input type="text" class="form-control" name="home_page[newsletter_sub_heading]" value="{{ isset($pageData->newsletter_sub_heading) ? $pageData->newsletter_sub_heading : '' }}">
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Newsletter Background') }}</label>
						        <input type="file" class="dropify" name="home_page_media[newsletter_bg_image]" data-default-file="{{ isset($pageMedia->newsletter_bg_image) ? asset('public/uploads/media/'.$pageMedia->newsletter_bg_image) : '' }}">
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


