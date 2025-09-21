<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('expenses.store') }}" enctype="multipart/form-data">
	@csrf
	<div class="row px-2">
	    <div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Expense Date') }}</label>						
				<input type="text" class="form-control datetimepicker" name="expense_date" value="{{ old('expense_date', now()) }}" required>
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Expense Category') }}</label>						
				<select class="form-control auto-select select2" data-selected="{{ old('expense_category_id') }}" name="expense_category_id"  required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(\App\Models\ExpenseCategory::all() as $expense_category)
					<option value="{{ $expense_category->id }}">{{ $expense_category->name }}</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Bank Account') }}</label>						
				<select class="form-control auto-select select2" data-selected="{{ old('bank_account_id') }}" name="bank_account_id"  required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(\App\Models\BankAccount::active()->get() as $bank_account)
					<option value="{{ $bank_account->id }}" data-balance="{{ $bank_account->current_balance }}">
						{{ $bank_account->bank_name }} - {{ $bank_account->account_name }} ({{ $bank_account->currency->name }})
						- Balance: {{ decimalPlace($bank_account->current_balance, currency($bank_account->currency->name)) }}
					</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Amount') }}</label>	
				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="amount-addon">{{ currency(get_base_currency()) }}</span>
					</div>
					<input type="text" class="form-control float-field" name="amount" value="{{ old('amount') }}" aria-describedby="amount-addon" required>
				</div>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Reference') }}</label>						
				<input type="text" class="form-control" name="reference" value="{{ old('reference') }}">
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Note') }}</label>						
				<textarea class="form-control" name="note">{{ old('note') }}</textarea>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Attachment') }}</label></br>						
				<input type="file" name="attachment">
			</div>
		</div>
	
		<div class="col-md-12 mt-2">
		    <div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
		    </div>
		</div>
	</div>
</form>
