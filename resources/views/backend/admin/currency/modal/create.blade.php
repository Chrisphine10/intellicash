<form method="post" class="ajax-screen-submit" autocomplete="off" action="{{ route('currency.store') }}" enctype="multipart/form-data">
	@csrf
	<div class="row px-2">
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Full Name') }}</label>						
				<input type="text" class="form-control" name="full_name" value="{{ old('full_name') }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Code') }}</label>						
				<input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="USD" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Exchange Rate') }}</label>						
				<input type="text" class="form-control float-field" name="exchange_rate" value="{{ old('exchange_rate') }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Base Currency') }}</label>						
				<select class="form-control auto-select" data-selected="{{ old('base_currency') }}" name="base_currency"  required>
					<option value="">{{ _lang('Select One') }}</option>
					<option value="0">{{ _lang('No') }}</option>
					<option value="1">{{ _lang('Yes') }}</option>
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Status') }}</label>						
				<select class="form-control auto-select" data-selected="{{ old('status') }}" name="status"  required>
					<option value="">{{ _lang('Select One') }}</option>
					<option value="1">{{ _lang('Active') }}</option>
					<option value="0">{{ _lang('Deactivate') }}</option>
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
