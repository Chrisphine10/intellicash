@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Packages') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('admin.packages.create') }}"><i class="ti-plus mr-1"></i>{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="packages_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Cost') }}</th>
							<th>{{ _lang('Package Type') }}</th>
							<th>{{ _lang('Discount') }}</th>
							<th class="text-center">{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Popular') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($packages as $package)
					    <tr data-id="row_{{ $package->id }}">
							<td class='name'>{{ $package->name }}</td>
							<td class='cost'>{{ decimalPlace($package->cost, currency_symbol()) }}</td>
							<td class='package_type'>{{ ucwords($package->package_type) }}</td>
							<td class='discount'>{{ $package->discount }}%</td>
							<td class='status text-center'>{!! xss_clean(status($package->status)) !!}</td>
							<td class='is_popular text-center'>
								@if($package->is_popular == 1)
								{!! xss_clean(show_status(_lang('Yes'), 'success')) !!}
								@else
								{!! xss_clean(show_status(_lang('No'), 'danger')) !!}
								@endif
							</td>
							
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('admin.packages.destroy', $package['id']) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('admin.packages.edit', $package['id']) }}" class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil"></i> {{ _lang('Edit') }}</a>
										<a href="{{ route('admin.packages.show', $package['id']) }}" class="dropdown-item dropdown-view dropdown-view"><i class="ti-eye"></i> {{ _lang('View') }}</a>
										<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i> {{ _lang('Delete') }}</button>
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