@extends('layouts.app')

@section('content')
<div class="row">
    <div class="{{ $alert_col }}">
        <div class="card">
		    <div class="card-header">
				<span class="panel-title">{{ _lang('Sent Items') }}</span>
			</div>
			<div class="card-body px-0 pt-0">
                @if($messages->isEmpty())
                    <p class="text-center py-3">{{ _lang('No sent messages !') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover border-bottom">
                            <thead class="bg-light">
                                <tr>
                                    <th class="text-dark pl-4">{{ _lang('Recipient') }}</th>
                                    <th class="text-dark">{{ _lang('Subject') }}</th>
                                    <th class="text-dark">{{ _lang('Date') }}</th>
                                    <th class="text-dark"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($messages as $message)
                                    <tr>
                                        <td class="pl-4">{{ $message->recipient->name }}</td>
                                        <td>{{ $message->subject }}</td>
                                        <td>{{ $message->created_at->format('M d, Y h:i A') }}</td>
                                        <td>
                                            <a class="btn btn-primary btn-xs" href="{{ route('messages.show', $message->uuid) }}">
                                                {{ _lang('View Message') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination pl-2">
                        {{ $messages->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
