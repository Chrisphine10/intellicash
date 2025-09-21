@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-4 offset-lg-4">
		<div class="card">
		    <div class="card-header text-center">
				<span class="panel-title">{{ _lang('Restore Backup') }}</span>
			</div>
			<div class="card-body">
                <form action="{{ route('admin.backup.restore') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group mb-3">
                        <label for="backup_file">{{ _lang('Select Backup File') }}</label>
                        <select name="backup_file" id="backup_file" class="form-control">
                            <option value="">{{ _lang('Select One') }}</option>
                            @foreach ($backupFiles as $file)
                                <option value="{{ $file }}">{{ str_replace('backups/', '', $file) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mb-4">
                        <label for="upload_file">{{ _lang('Or Upload Backup File') }}</label>
                        <input type="file" name="upload_file" id="upload_file" class="file-uploader" data-placeholder="{{ _lang('Select a file') }}">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-undo mr-1"></i>{{ _lang('Restore Backup') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection