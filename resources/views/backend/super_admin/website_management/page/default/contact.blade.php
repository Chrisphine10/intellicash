@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('admin.pages.default_pages.store') }}" enctype="multipart/form-data">
	<div class="row">
		<div class="col-lg-8 offset-lg-2">
			<div class="card">
				<div class="card-header d-flex align-items-center justify-content-between">
					<span class="panel-title">{{ _lang('Update Contact Page') }}</span>
					<a href="{{ route('admin.pages.default_pages') }}" class="btn btn-outline-primary btn-xs"><i class="fas fa-chevron-left mr-2"></i>{{ _lang('Back') }}</a>
				</div>
				<div class="card-body">
					@csrf
					<div class="row">
						<div class="col-md-6">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Title') }}</label>
						        <input type="text" class="form-control" name="contact_page[title]" value="{{ isset($pageData->title) ? $pageData->title : '' }}" required>
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
						        <label class="control-label">{{ _lang('Contact Form Heading') }}</label>
						        <input type="text" class="form-control" name="contact_page[contact_form_heading]" value="{{ isset($pageData->contact_form_heading) ? $pageData->contact_form_heading : '' }}" required>
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Contact Form Sub Heading') }}</label>
						        <input type="text" class="form-control" name="contact_page[contact_form_sub_heading]" value="{{ isset($pageData->contact_form_heading) ? $pageData->contact_form_heading : '' }}">
					        </div>
					    </div>

						<div class="col-md-12 my-4 d-flex align-items-center justify-content-between">
					        <h5><b>{{ _lang('Contact Informations') }}</b></h5>
							<button type="button" id="add-row" class="btn btn-outline-primary btn-xs">{{ _lang('Add Row') }}</button>
					    </div>

						<div class="col-md-12" id="contact-informations">
							@if(isset($pageData->contact_info_heading))
							@foreach($pageData->contact_info_heading as $contact_info_heading)
							<div class="row">
								<div class="col-lg-12">
									<div class="form-group">
										<label class="control-label d-flex">{{ _lang('Heading') }} <button type="button" class="remove-row bg-danger text-white border border-danger px-2 rounded ml-auto order-3"><i class="fas fa-minus-circle"></i></button></label>
										<input type="text" class="form-control" name="contact_page[contact_info_heading][]" value="{{ $contact_info_heading }}" required>
									</div>
								</div>

								<div class="col-lg-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Content') }}</label>
										<textarea class="form-control" name="contact_page[contact_info_content][]" required>{{ isset($pageData->contact_info_content[$loop->index]) ? $pageData->contact_info_content[$loop->index] : '' }}</textarea>
									</div>
								</div>
							</div>
							@endforeach
							@endif
						</div>	
						
						<div class="col-md-12 my-4">
					        <h5><b>{{ _lang('Social Links') }}</b></h5>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Facebook Link') }}</label>
						        <input type="text" class="form-control" name="contact_page[facebook_link]" value="{{ isset($pageData->facebook_link) ? $pageData->facebook_link : '' }}">
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Linkedin Link') }}</label>
						        <input type="text" class="form-control" name="contact_page[linkedin_link]" value="{{ isset($pageData->linkedin_link) ? $pageData->linkedin_link : '' }}">
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Twitter Link') }}</label>
						        <input type="text" class="form-control" name="contact_page[twitter_link]" value="{{ isset($pageData->twitter_link) ? $pageData->twitter_link : '' }}">
					        </div>
					    </div>

						<div class="col-md-12">
					        <div class="form-group">
						        <label class="control-label">{{ _lang('Youtube Link') }}</label>
						        <input type="text" class="form-control" name="contact_page[youtube_link]" value="{{ isset($pageData->youtube_link) ? $pageData->youtube_link : '' }}">
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

@section('js-script')
<script>
(function ($) {
	"use strict";
	$(document).on('click', '#add-row', function(){
		$('#contact-informations').append(`<div class="row">
			<div class="col-lg-12">
				<div class="form-group">
					<label class="control-label d-flex justify-content-between">{{ _lang('Heading') }} <button type="button" class="remove-row bg-danger text-white border border-danger px-2 rounded"><i class="fas fa-minus-circle"></i></button></label>
					<input type="text" class="form-control" name="contact_page[contact_info_heading][]" value="" required>
				</div>
			</div>

			<div class="col-lg-12">
				<div class="form-group">
					<label class="control-label">{{ _lang('Content') }}</label>
					<textarea class="form-control" name="contact_page[contact_info_content][]" required></textarea>
				</div>
			</div>
		</div>`);
	});

	$(document).on('click', '.remove-row', function(){
		$(this).closest('.row').remove();
	});

})(jQuery);
</script>
@endsection


