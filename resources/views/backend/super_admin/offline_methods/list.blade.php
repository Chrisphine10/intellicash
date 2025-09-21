@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Offline Gateways') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('admin.offline_methods.create') }}"><i class="ti-plus"></i> {{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="offline_methods_table" class="table data-table">
					<thead>
					    <tr>
							<th>{{ _lang('Image') }}</th>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($paymentGateways as $paymentGateway)
					    <tr data-id="row_{{ $paymentGateway->id }}">
							<td class='image'>
								<img class="thumb-sm img-thumbnail" src="{{ asset('public/uploads/media/'.$paymentGateway->image) }}"/>
							</td>
							<td class='name'>{{ $paymentGateway->name }}</td>
							<td class='status'>{!! xss_clean(status($paymentGateway->status)) !!}</td>
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('admin.offline_methods.destroy', $paymentGateway['id']) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('admin.offline_methods.edit', $paymentGateway['id']) }}" class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil-alt"></i> {{ _lang('Edit') }}</a>
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