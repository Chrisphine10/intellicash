<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('bank_accounts.update', $id) }}" enctype="multipart/form-data">
	@csrf
	<input name="_method" type="hidden" value="PATCH">
	<div class="row px-2">
		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Opening Date') }}</label>						
				<input type="text" class="form-control datepicker" name="opening_date" value="{{ $bankAccount->getRawOriginal('opening_date') }}" readonly>
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Bank Name') }}</label>						
				<input type="text" class="form-control" name="bank_name" value="{{ $bankAccount->bank_name }}" required>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Account Name') }}</label>						
				<input type="text" class="form-control" name="account_name" value="{{ $bankAccount->account_name }}" required>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Account Number') }}</label>						
				<input type="text" class="form-control" name="account_number" value="{{ $bankAccount->account_number }}" required>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Currency') }}</label>						
				<select class="form-control select2 auto-select" data-selected="{{ $bankAccount->currency_id }}" name="currency_id" required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(\App\Models\Currency::where('status', 1)->get() as $currency)
					<option value="{{ $currency->id }}">{{ $currency->full_name }} ({{ $currency->name }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Opening Balance') }}</label>						
				<input type="text" class="form-control float-field" name="opening_balance" value="{{ $bankAccount->opening_balance }}" readonly>
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Description') }}</label>						
				<textarea class="form-control" name="description">{{ $bankAccount->description }}</textarea>
			</div>
		</div>
	
		<div class="col-lg-12 mt-2">
			<div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i> {{ _lang('Update') }}</button>
		    </div>
		</div>
	</div>
</form>

