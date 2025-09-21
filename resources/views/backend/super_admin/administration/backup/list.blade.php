@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-sm-flex align-items-center justify-content-between">
				<span class="panel-title">{{ _lang('Data Backup') }}</span>
				<div class="mt-2 sm-mt-0">
					<a class="btn btn-danger btn-xs" href="{{ route('admin.backup.restore') }}"><i class="fas fa-undo mr-1"></i>{{ _lang('Restore Backup') }}</a>
					<a class="btn btn-primary btn-xs" href="{{ route('admin.backup.create') }}"><i class="ti-plus mr-1"></i>{{ _lang('New Backup') }}</a>
				</div>
			</div>
			<div class="card-body">
				<table id="database_backups_table" class="table data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Created') }}</th>
						    <th>{{ _lang('File Name') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					  @foreach($backupFiles as $file)
					    <tr>
							<td class='created_at'>{{ date(get_date_format().' '.get_time_format(), filemtime(storage_path('app/private/' . $file))) }}</td>
							<td class='file'>{{ str_replace('backups/', '', $file) }}</td>
							<td class="text-center">
								<span class="dropdown">
									<button class="btn btn-outline-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									{{ _lang('Action') }}
									
									</button>
									<form action="{{ route('admin.backup.destroy', str_replace('backups/', '', $file)) }}" method="post">
										@csrf
										<input name="_method" type="hidden" value="DELETE">

										<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
											<a href="{{ route('admin.backup.download', str_replace('backups/', '', $file)) }}" class="dropdown-item dropdown-view"><i class="ti-download mr-2"></i>{{ _lang('Download') }}</a>
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
@endsection