@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Teams') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add Team Member') }}" href="{{ route('admin.teams.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="teams_table" class="table data-table">
					<thead>
					    <tr>
                            <th>{{ _lang('Image') }}</th>
						    <th>{{ _lang('Name') }}</th>
						    <th>{{ _lang('Role') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($teams as $team)
					    <tr data-id="row_{{ $team->id }}">
                            <td class='image'><img src="{{ asset('public/uploads/media/'.$team->image) }}" class="thumb-sm img-thumbnail"/></td>
							<td class='name'>{{ $team->translation->name }}</td>
							<td class='role'>{{ $team->translation->role }}</td>

							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('admin.teams.destroy', $team['id']) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('admin.teams.edit', $team['id']) }}" data-title="{{ _lang('Update Details') }}" class="dropdown-item dropdown-view ajax-modal"><i class="ti-pencil"></i>&nbsp;{{ _lang('Edit') }}</a>
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