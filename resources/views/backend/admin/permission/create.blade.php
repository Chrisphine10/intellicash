@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-xl-8 col-lg-12 offset-xl-2">
        <form method="post" id="permissions" class="validate" autocomplete="off" action="{{ route('permission.store') }}" enctype="multipart/form-data">
            @csrf
			<div class="row">
                <div class="col-lg-12">
                    <div class="card">
						<div class="card-header d-flex align-items-center">
							<span class="panel-title">{{ _lang('Access Control') }}</span>
						</div>
                        <div class="card-body">
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Select Role') }}</label>
                                    <select class="form-control auto-select" data-selected="{{ $role_id ?? '' }}" id="user_role" name="role_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\Role::all() as $role)
										<option value="{{ $role->id }}">{{ $role->name }}</option>
										@endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

			@if($role_id != '')
            <div class="card mt-2">
                <div class="card-body">
                    <div class="row">
						<div class="col-lg-12">
							<table class="table table-bordered">
								<thead>
									<tr>
										<th>{{ _lang('Module') }}</th>
										<th>{{ _lang('Permissions') }}</th>
									</tr>
								</thead>
								@foreach($permission as $key => $val)
									@php
									$string = isset(explode("\\",$key)[4]) ? str_replace("Controller", "", explode("\\",$key)[4]) : str_replace("Controller", "", explode("\\",$key)[3]);
									$array = preg_split('/(?=[A-Z])/', $string);
									$moduleName = implode(' ', $array);
									@endphp
									<tr>
										<td><h5>{{ $moduleName }}</h5></td>
										<td>
										@foreach($val as $name => $url)
											@php $display = str_replace("index", "list", $name); @endphp
											@php $display = array_reverse(explode('.', $display)); @endphp
															
											<div class="custom-control custom-checkbox permission-checkbox">
												<input type="checkbox" class="custom-control-input"
													name="permissions[]" value="{{ $name }}"
													id="customCheck{{ $loop->parent->index.$loop->index }}"
													{{ array_search($name, $permission_list) !== FALSE ? "checked" : "" }}>
												<label class="custom-control-label"
													for="customCheck{{ $loop->parent->index.$loop->index }}">{{ strtoupper(str_replace('_', ' ', $display[0])) }}</label>
											</div>
										@endforeach
										</td>
									</tr>
								@endforeach
							</table>
						</div>

                        <div class="col-lg-12 mt-2">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Permission') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			@endif
        </form>
    </div>
</div>
@endsection