@extends('layouts.app')

@section('content')
<div class="row">
    <div class="{{ $alert_col }}">
        <div class="card">
            <div class="card-body">
                <h5><b>{{ $message->subject }}</b></h5>
                <p class="mt-2"><strong>{{ _lang('From') }}:</strong> {{ $message->sender->name }} ({{ $message->sender->user_type }})</p>
                <p><strong>{{ _lang('To') }}:</strong> {{ $message->recipient->name }} ({{ $message->recipient->user_type }})</p>
                <p class="mt-3">{{ $message->body }}</p>

                <!-- Display attachments -->
                @if($message->attachments->count() > 0)
                    <h5 class="mt-4">{{ _lang('Attachments') }}</h5>
                    <ul class="list-group mt-2">
                        @foreach($message->attachments as $attachment)
                            <li class="list-group-item">
                                <a href="{{ route('messages.download_attachment', $attachment->id) }}"><i class="fas fa-paperclip mr-1"></i>{{ $attachment->file_name }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <!-- Display Replies -->
                @if ($message->replies->count() > 0)
                    <h5 class="mt-4">{{ _lang('Replies') }}</h5>
                    <ul class="list-group mt-2">
                        @foreach($message->replies as $reply)
                            <li class="list-group-item">
                                <strong>{{ $reply->sender->name }}:</strong> {{ $reply->body }}
                                @if($reply->attachments->count() > 0)
                                    <ul class="list-group mt-2">
                                        @foreach($reply->attachments as $attachment)
                                            <li class="list-group-item">
                                                <a href="{{ route('messages.download_attachment', $attachment->id) }}"><i class="fas fa-paperclip mr-1"></i>{{ $attachment->file_name }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ route('messages.reply', $message->uuid) }}" class="btn btn-primary btn-xs mt-4"><i class="fas fa-reply mr-2"></i>{{ _lang('Reply') }}</a>
                <a href="{{ route('messages.inbox') }}" class="btn btn-danger btn-xs mt-4"><i class="fas fa-envelope mr-2"></i>{{ _lang('Inbox') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
