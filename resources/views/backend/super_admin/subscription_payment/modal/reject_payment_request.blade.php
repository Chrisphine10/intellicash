<form method="post" class="validate" autocomplete="off" action="{{ route('admin.subscription_payments.reject_payment_requests', $id) }}">
	@csrf
	<div class="row px-2">
	    <div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Rejection Reason') }}</label>
				<textarea class="form-control" name="reason" required></textarea>
			</div>
		</div>

		<div class="col-md-12 mt-2">
		    <div class="form-group">
			    <button type="submit" class="btn btn-primary "><i class="ti-check-box mr-2"></i>{{ _lang('Submit') }}</button>
		    </div>
		</div>
	</div>
</form>
