@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('admin.pages.default_pages.store') }}" enctype="multipart/form-data">
	<div class="row">
		<div class="col-lg-8 offset-lg-2">
			<div class="card">
				<div class="card-header text-center">
					<span class="panel-title">{{ _lang('Header & Footer Settings') }}</span>
				</div>
				<div class="card-body">
					@csrf
					<div class="row">
				
						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Footer Color') }}</label>
						        <input type="text" class="form-control" name="header_footer_page[footer_color]" value="{{ isset($pageData->footer_color) ? $pageData->footer_color : '#167b6a' }}" placeholder="#167b6a" required>
					        </div>
					    </div>

						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Footer Text Color') }}</label>
						        <input type="text" class="form-control" name="header_footer_page[footer_text_color]" value="{{ isset($pageData->footer_text_color) ? $pageData->footer_text_color : '#FFF' }}" placeholder="#FFF" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Widget 1 Heading') }}</label>
						        <input type="text" class="form-control" name="header_footer_page[widget_1_heading]" value="{{ isset($pageData->widget_1_heading) ? $pageData->widget_1_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Widget 1 Content') }}</label>
						        <textarea class="form-control" name="header_footer_page[widget_1_content]">{{ isset($pageData->widget_1_content) ? $pageData->widget_1_content : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Widget 2 Heading') }}</label>
						        <input type="text" class="form-control" name="header_footer_page[widget_2_heading]" value="{{ isset($pageData->widget_2_heading) ? $pageData->widget_2_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Widget 2 Menus') }}</label>
						        <select class="form-control multi-selector auto-multiple-select" data-placeholder="{{ _lang('Select Pages') }}" name="header_footer_page[widget_2_menus][]" data-selected="{{ isset($pageData->widget_2_menus) ? json_encode($pageData->widget_2_menus) : '' }}" multiple>
									<option value="home">{{ _lang('Home') }}</option>
									<option value="about">{{ _lang('About') }}</option>
									<option value="features">{{ _lang('Features') }}</option>
									<option value="pricing">{{ _lang('Pricing') }}</option>
									<option value="blogs">{{ _lang('Blogs') }}</option>
									<option value="faq">{{ _lang('FAQ') }}</option>
									<option value="contact">{{ _lang('Contact') }}</option>
									<option value="privacy-policy">{{ _lang('Privacy Policy') }}</option>
									<option value="terms-condition">{{ _lang('Terms & Condition') }}</option>
									@foreach(\App\Models\Page::active()->get() as $page)
									<option value="{{ $page->slug }}">{{ $page->translation->title }} ({{ _lang('Custom') }})</option>
									@endforeach
								</select>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Widget 3 Heading') }}</label>
						        <input type="text" class="form-control" name="header_footer_page[widget_3_heading]" value="{{ isset($pageData->widget_3_heading) ? $pageData->widget_3_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Widget 3 Menus') }}</label>
						        <select class="form-control multi-selector auto-multiple-select" data-placeholder="{{ _lang('Select Pages') }}" name="header_footer_page[widget_3_menus][]" data-selected="{{ isset($pageData->widget_3_menus) ? json_encode($pageData->widget_3_menus) : '' }}" multiple>
								<option value="home">{{ _lang('Home') }}</option>
									<option value="about">{{ _lang('About') }}</option>
									<option value="features">{{ _lang('Features') }}</option>
									<option value="pricing">{{ _lang('Pricing') }}</option>
									<option value="blogs">{{ _lang('Blogs') }}</option>
									<option value="faq">{{ _lang('FAQ') }}</option>
									<option value="contact">{{ _lang('Contact') }}</option>
									<option value="privacy-policy">{{ _lang('Privacy Policy') }}</option>
									<option value="terms-condition">{{ _lang('Terms & Condition') }}</option>
									@foreach(\App\Models\Page::active()->get() as $page)
									<option value="{{ $page->slug }}">{{ $page->translation->title }} ({{ _lang('Custom') }})</option>
									@endforeach
								</select>
					        </div>
					    </div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Language') }}</label>
								<select class="form-control" name="model_language" required>
									{{ load_language(get_language()) }}
								</select>
							</div>
						</div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Copyright Text') }}</label>
						        <input type="text" class="form-control" name="header_footer_page[copyright_text]" value="{{ isset($pageData->copyright_text) ? $pageData->copyright_text : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Payment Gateway Image') }}</label>
						        <input type="file" class="dropify" name="header_footer_page_media[payment_gateway_image]" data-default-file="{{ isset($pageMedia->payment_gateway_image) ? asset('public/uploads/media/'.$pageMedia->payment_gateway_image) : '' }}">
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Custom CSS') }}</label>
						        <textarea class="form-control" rows="8" name="header_footer_page[custom_css]">{{ isset($pageData->custom_css) ? $pageData->custom_css : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Custom JS') }}</label>
						        <textarea class="form-control" rows="8" name="header_footer_page[custom_js]" placeholder="Write Code Without <script> tag">{{ isset($pageData->custom_js) ? $pageData->custom_js : '' }}</textarea>
					        </div>
					    </div>

						<div class="col-md-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary mt-2"><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
							</div>
						</div>
					</div>
				</div>
			</div>
	    </div>
	</div>
</form>
@endsection


