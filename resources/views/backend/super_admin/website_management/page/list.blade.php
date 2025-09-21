@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Pages') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('admin.pages.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="pages_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Title') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th>{{ _lang('Created') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($pages as $page)
					    <tr data-id="row_{{ $page->id }}">
							<td class='title'>{{ $page->translation->title }}</td>
							<td class='status'>{!! xss_clean(status($page->status)) !!}</td>
							<td class='created_at'>{{ $page->created_at }}</td>

							<td class="text-center">
								<div class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('admin.pages.destroy', $page['id']) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('admin.pages.edit', $page['id']) }}" class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
										<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
									</div>
								  </form>
								</div>
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