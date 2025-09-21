@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Brands') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add New Brand') }}" href="{{ route('admin.brands.create') }}"><i class="ti-plus mr-1"></i>{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="brands_table" class="table data-table">
					<thead>
					    <tr>
							<th>{{ _lang('Logo') }}</th>
						    <th>{{ _lang('Name') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($brands as $brand)
					    <tr data-id="row_{{ $brand->id }}">
							<td class='logo'><img src="{{ asset('public/uploads/media/'.$brand->logo) }}" class="thumb-sm img-thumbnail"></td>
							<td class='name'>{{ $brand->name }}</td>
							
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('admin.brands.destroy', $brand['id']) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('admin.brands.edit', $brand['id']) }}" data-title="{{ _lang('Update Brand') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil mr-1"></i>{{ _lang('Edit') }}</a>
										<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash mr-1"></i>{{ _lang('Delete') }}</button>
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