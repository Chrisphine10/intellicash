@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Testimonials') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add Testimonial') }}" href="{{ route('admin.testimonials.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="testimonials_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Image') }}</th>
						    <th>{{ _lang('Name') }}</th>
						    <th>{{ _lang('Testimonial') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($testimonials as $testimonial)
					    <tr data-id="row_{{ $testimonial->id }}">
							<td class='image'><img src="{{ asset('public/uploads/media/'.$testimonial->image) }}" class="thumb-sm img-thumbnail"/></td>
							<td class='name'>{{ $testimonial->translation->name }}</td>
							<td class='testimonial'>{{ Str::limit($testimonial->translation->testimonial, 100) }}</td>
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('admin.testimonials.destroy', $testimonial->id) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('admin.testimonials.edit', $testimonial->id) }}" data-title="{{ _lang('Update Testimonial') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
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