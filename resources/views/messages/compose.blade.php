@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
		    <div class="card-header text-center">
				<span class="panel-title">{{ _lang('Compose New Message') }}</span>
			</div>
			<div class="card-body">
                <form action="{{ route('messages.send') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label for="recipient">{{ _lang('Recipient') }}</label>
                        <select name="recipient_id" class="form-control select2 auto-select no-msg" data-selected="{{ old('recipient_id') }}" required>
                            <option value="">{{ _lang('Select One') }}</option>
                            @foreach(\App\Models\User::where('id', '!=', auth()->id())->get() as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">{{ _lang('Subject') }}</label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                    </div>

                    <div class="form-group">
                        <label for="body">{{ _lang('Message') }}</label>
                        <textarea name="body" class="form-control" rows="5" required>{{ old('body') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label for="attachments">{{ _lang('Attachments') }}</label>
                        <input type="file" name="attachments[]" class="file-uploader" data-placeholder="{{ _lang('Attachments') }}" multiple>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-2"></i>{{ _lang('Send') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
