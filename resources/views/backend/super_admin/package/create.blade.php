@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Add New Package') }}</span>
			</div>
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="{{ route('admin.packages.store') }}" enctype="multipart/form-data">
					@csrf
					<div class="row">
						<div class="col-lg-12">
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Package Name') }}</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Package Type') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ old('package_type') }}" name="package_type" required>
										<option value="">{{ _lang('Select One') }}</option>
										<option value="monthly">{{ _lang('Monthly') }}</option>
										<option value="yearly">{{ _lang('Yearly') }}</option>
										<option value="lifetime">{{ _lang('Lifetime') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Cost') }} ({{ currency_symbol() }})</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control float-field" name="cost" value="{{ old('cost') }}" required>
								</div>
							</div>
					
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Discount') }} (%)</label>						
								<div class="col-xl-9">
									<input type="text" class="form-control float-field" name="discount" value="{{ old('discount', 0) }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Trial Days') }}</label>						
								<div class="col-xl-9">
									<input type="number" class="form-control" name="trial_days" value="{{ old('trial_days', 0) }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ old('status', 1) }}" name="status" required>
										<option value="1">{{ _lang('Active') }}</option>
										<option value="0">{{ _lang('Disabled') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Is Popular') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ old('is_popular', 0) }}" name="is_popular" required>
										<option value="0">{{ _lang('No') }}</option>
										<option value="1">{{ _lang('Yes') }}</option>
									</select>
								</div>
							</div>
						
							<hr>
							<div class="form-group row">					
								<div class="col-xl-9 offset-xl-3">
									<h5 class="text-info"><strong>{{ _lang('Manage Package Features') }}</strong></h5>
								</div>
							</div>			
							<hr>

							<div class="form-group row align-items-center">
								<label class="col-xl-3 col-form-label">{{ _lang('Role Based Users') }}</label>						
								<div class="col-xl-7">
									<input type="number" class="form-control" name="user_limit" value="{{ old('user_limit') != '-1' ? old('user_limit') : '' }}" placeholder="5">
								</div>

								<div class="col-xl-2">
									<div class="form-check">
										<label class="form-check-label text-danger">
											<input type="checkbox" class="form-check-input no-msg" name="user_limit" value="-1" {{ old('user_limit') == '-1' ? 'checked' : '' }}>{{ _lang('UNLIMITED') }}
										</label>
									</div>
								</div>
							</div>

							<div class="form-group row align-items-center">
								<label class="col-xl-3 col-form-label">{{ _lang('Member Limit') }}</label>						
								<div class="col-xl-7">
									<input type="number" class="form-control" name="member_limit" value="{{ old('member_limit') != '-1' ? old('member_limit') : '' }}" placeholder="100">
								</div>

								<div class="col-xl-2">
									<div class="form-check">
										<label class="form-check-label text-danger">
											<input type="checkbox" class="form-check-input no-msg" name="member_limit" value="-1" {{ old('member_limit') == '-1' ? 'checked' : '' }}>{{ _lang('UNLIMITED') }}
										</label>
									</div>
								</div>
							</div>

							<div class="form-group row align-items-center">
								<label class="col-xl-3 col-form-label">{{ _lang('Branch Limit') }}</label>						
								<div class="col-xl-7">
									<input type="number" class="form-control" name="branch_limit" value="{{ old('branch_limit') != '-1' ? old('branch_limit') : '' }}" placeholder="100">
								</div>

								<div class="col-xl-2">
									<div class="form-check">
										<label class="form-check-label text-danger">
											<input type="checkbox" class="form-check-input no-msg" name="branch_limit" value="-1" {{ old('branch_limit') == '-1' ? 'checked' : '' }}>{{ _lang('UNLIMITED') }}
										</label>
									</div>
								</div>
							</div>

							<div class="form-group row align-items-center">
								<label class="col-xl-3 col-form-label">{{ _lang('Account Type Limit') }}</label>						
								<div class="col-xl-7">
									<input type="number" class="form-control" name="account_type_limit" value="{{ old('account_type_limit') != '-1' ? old('account_type_limit') : '' }}" placeholder="100">
								</div>

								<div class="col-xl-2">
									<div class="form-check">
										<label class="form-check-label text-danger">
											<input type="checkbox" class="form-check-input no-msg" name="account_type_limit" value="-1" {{ old('account_type_limit') == '-1' ? 'checked' : '' }}>{{ _lang('UNLIMITED') }}
										</label>
									</div>
								</div>
							</div>

							<div class="form-group row align-items-center">
								<label class="col-xl-3 col-form-label">{{ _lang('Account Limit') }}</label>						
								<div class="col-xl-7">
									<input type="number" class="form-control" name="account_limit" value="{{ old('account_limit') != '-1' ? old('account_limit') : '' }}" placeholder="100">
								</div>

								<div class="col-xl-2">
									<div class="form-check">
										<label class="form-check-label text-danger">
											<input type="checkbox" class="form-check-input no-msg" name="account_limit" value="-1" {{ old('account_limit') == '-1' ? 'checked' : '' }}>{{ _lang('UNLIMITED') }}
										</label>
									</div>
								</div>
							</div>

							<div class="form-group row align-items-center">
								<label class="col-xl-3 col-form-label">{{ _lang('Member Portal') }}</label>						
								<div class="col-xl-7">
									<select class="form-control auto-select" data-selected="{{ old('member_portal') }}" name="member_portal" required>
										<option value="">{{ _lang('Select One') }}</option>
										<option value="0">{{ _lang('No') }}</option>
										<option value="1">{{ _lang('Yes') }}</option>
									</select>
								</div>
							</div>
						
							<div class="form-group row mt-2">
								<div class="col-xl-9 offset-xl-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
								</div>
							</div>
						</div>
					</div>
			    </form>
			</div>
		</div>
    </div>
</div>
@endsection


