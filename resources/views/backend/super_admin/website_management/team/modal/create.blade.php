<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('admin.teams.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row px-2">
        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Name') }}</label>
                <input type="text" class="form-control" name="trans[name]" value="{{ old('trans.name') }}" required>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Role') }}</label>
                <input type="text" class="form-control" name="trans[role]" value="{{ old('trans.role') }}" required>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Image') }}</label>
                <input type="file" class="form-control dropify" name="image">
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Description') }}</label>
                <textarea class="form-control" name="trans[description]">{{ old('trans.description') }}</textarea>
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

        <div class="col-md-12 mt-2">
            <div class="form-group">
                <button type="submit" class="btn btn-primary "><i class="ti-check-box mr-2"></i>{{ _lang('Save') }}</button>
            </div>
        </div>
    </div>
</form>