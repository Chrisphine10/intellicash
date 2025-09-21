<form method="post" class="ajax-screen-submit" autocomplete="off" action="{{ route('roles.update', $id) }}" enctype="multipart/form-data">
	@csrf
	<input name="_method" type="hidden" value="PATCH">

	<div class="col-md-12">
		<div class="form-group">
		   <label class="control-label">{{ _lang('Name') }}</label>
		   <input type="text" class="form-control" name="name" value="{{ $role->name }}" required>
		</div>
	</div>

	<div class="col-md-12">
		<div class="form-group">
		   <label class="control-label">{{ _lang('Description') }}</label>
		   <textarea class="form-control" name="description">{{ $role->description }}</textarea>
		</div>
	</div>

	<div class="form-group">
	    <div class="col-md-12">
		    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
	    </div>
	</div>
</form>

