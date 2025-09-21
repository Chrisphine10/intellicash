@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
		    <div class="card-header text-center">
				<span class="panel-title">{{ _lang('Reply to') }}: {{ $message->subject }}</span>
			</div>
			<div class="card-body">
                <form action="{{ route('messages.sendReply', $message->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="body">{{ _lang('Reply') }}</label>
                        <textarea name="body" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachments">{{ _lang('Attachments') }}</label>
                        <input type="file" name="attachments[]" class="file-uploader" data-placeholder="{{ _lang('Attachments') }}" multiple>
                    </div>
                    <button type="submit" class="btn btn-primary btn-xs"><i class="fas fa-paper-plane mr-2"></i>{{ _lang('Send Reply') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
