@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center">
				<h4 class="header-title">{{ _lang('User Roles') }}</h4>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Create Role') }}" href="{{ route('roles.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="roles_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Description') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($roles as $role)
					    <tr data-id="row_{{ $role->id }}">
							<td class='name'>{{ $role->name }}</td>
							<td class='description'>{{ $role->description }}</td>

							<td class="text-center">
								<span class="dropdown">
									<button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									{{ _lang('Action') }}
									
									</button>
									<form action="{{ route('roles.destroy', $role['id']) }}" method="post">
										@csrf
										<input name="_method" type="hidden" value="DELETE">

										<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
											<a href="{{ route('roles.edit', $role['id']) }}" data-title="{{ _lang('Update Role') }}" class="dropdown-item ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
											<a href="{{ route('permission.show', $role->id) }}" class="dropdown-item"><i class="ti-lock mr-1"></i>{{ _lang('Access Control') }}</a>
											<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
										</div>
									</form>
								</span>
							</td>
					    </tr>
					    @endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection