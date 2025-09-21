@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Blog Posts') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('admin.posts.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="news_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Image') }}</th>
							<th>{{ _lang('Title') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($posts as $post)
					    <tr data-id="row_{{ $post->id }}">
							<td class='image'><img src="{{ asset('public/uploads/media/'.$post->image) }}" class="thumb-sm img-thumbnail"/></td>
							<td class='title'>{{ $post->translation->title }}</td>
							<td class='status'>{!! xss_clean(status($post->status)) !!}</td>

							<td class="text-center">
								<span class="dropdown">
									<button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
										{{ _lang('Action') }}		  
									</button>
									<form action="{{ route('admin.posts.destroy', $post['id']) }}" method="post">
										@csrf
										<input name="_method" type="hidden" value="DELETE">

										<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
											<a href="{{ route('admin.posts.edit', $post['id']) }}" class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
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