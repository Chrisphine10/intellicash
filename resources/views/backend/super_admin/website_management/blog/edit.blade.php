@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Update Post') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('admin.posts.update', $id) }}" enctype="multipart/form-data">
					@csrf
					<input name="_method" type="hidden" value="PATCH">
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Title') }}</label>
								<input type="text" class="form-control" name="trans[title]" value="{{ $post->translation->title }}" required>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Short Description') }}</label>
								<textarea class="form-control" name="trans[short_description]" required>{{ $post->translation->short_description }}</textarea>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Content') }}</label>
								<textarea class="form-control summernote" name="trans[content]">{{ $post->translation->content }}</textarea>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Image') }}</label>
								<input type="file" class="dropify" name="image" data-default-file="{{ $post->image != '' ? asset('public/uploads/media/'.$post->image) : '' }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
								<select class="form-control auto-select" data-selected="{{ $post->status }}" name="status" required>
									<option value="1">{{ _lang('Published') }}</option>
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

						<div class="col-md-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary "><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

@endsection


