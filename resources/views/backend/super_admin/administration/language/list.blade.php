@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Languages') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('admin.languages.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>

			<div class="card-body p-0">
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<tr>
								<th class="pl-4">{{ _lang('Flag') }}</th>
								<th>{{ _lang('Language Name') }}</th>
								<th class="text-center">{{ _lang('Action') }}</th>
							</tr>
						</thead>
						<tbody>
						  @foreach(get_language_list() as $language)
							<tr>
								<td class="pl-4"><img class="avatar avatar-xss avatar-circle me-2" src="{{ asset('public/backend/plugins/flag-icon-css/flags/1x1/'.explode('---', $language)[1].'.svg') }}"/></td>
								<td>{{ explode('---', $language)[0] }}</td>
								<td class="text-center">
									<span class="dropdown">
										<button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
										{{ _lang('Action') }}
										
										</button>
										<form action="{{ route('admin.languages.destroy', $language) }}" method="post">
											@csrf
											<input name="_method" type="hidden" value="DELETE">

											<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
												<a href="{{ route('admin.languages.edit', $language) }}" class="dropdown-item dropdown-view"><i class="ti-pencil mr-2"></i>{{ _lang('Edit All Translation') }}</a>
												<a href="{{ route('admin.languages.edit_website_language', $language) }}" class="dropdown-item dropdown-view"><i class="ti-pencil mr-2"></i>{{ _lang('Edit Webiste Translation') }}</a>
												<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash mr-2"></i>{{ _lang('Delete') }}</button>
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
</div>

@endsection


