@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="alert alert-info">
			<span><i class="far fa-question-circle mr-1"></i>{{ _lang('Base Currency exchange rate always 1.00') }}</span>
		</div>
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Currency List') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add New Currency') }}" href="{{ route('currency.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="currency_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Name') }}</th>
						    <th>{{ _lang('Code') }}</th>
							<th>{{ _lang('Exchange Rate') }}</th>
							<th>{{ _lang('Base Currency') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($currencys as $currency)
					    <tr data-id="row_{{ $currency->id }}">
							<td class='full_name'>{{ $currency->full_name }}</td>
							<td class='name'>{{ $currency->full_name }} ({{ $currency->name }})</td>
							<td class='exchange_rate'>{{ $currency->exchange_rate }}</td>
							<td class='base_currency'>{!! $currency->base_currency == 1 ? xss_clean(show_status(_lang('Yes'),'success')) : xss_clean(show_status(_lang('No'),'danger')) !!}</td>
							<td class='status'>{!! xss_clean(status($currency->status)) !!}</td>

							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  
								  </button>
								  @if($currency->base_currency == 0 )
								  <form action="{{ route('currency.destroy', $currency->id) }}" method="post">
									@csrf
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('currency.edit', $currency->id) }}" data-title="{{ _lang('Update Currency') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
										<a href="{{ route('currency.show', $currency->id) }}" data-title="{{ _lang('Currency Details') }}" class="dropdown-item dropdown-view ajax-modal"><i class="ti-eye"></i>&nbsp;{{ _lang('View') }}</a>
										<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
									</div>
								  </form>
								  @else
								  	<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('currency.edit', $currency->id) }}" data-title="{{ _lang('Update Currency') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
										<a href="{{ route('currency.show', $currency->id) }}" data-title="{{ _lang('Currency Details') }}" class="dropdown-item dropdown-view ajax-modal"><i class="ti-eye"></i>&nbsp;{{ _lang('View') }}</a>
									</div>
								  @endif
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