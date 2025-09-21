@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header text-center">
				<span class="panel-title">{{ _lang('Send email to all Subscriber') }}</span>
			</div>
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="{{ route('admin.email_subscribers.send_email') }}" enctype="multipart/form-data">
					@csrf
					<div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Subject') }}</label>						
                                <input type="text" class="form-control" value="{{ old('subject') }}" name="subject">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Message') }}</label>						
                                <textarea class="form-control summernote" name="message">{{ old('message') }}</textarea>
                            </div>
                        </div>
			
						<div class="col-md-12">
							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane mr-2"></i>{{ _lang('Send Email') }}</button>
								<a href="{{ url()->previous() }}" class="btn btn-dark"><i class="fas fa-undo-alt mr-2"></i>{{ _lang('Back') }}</a>
							</div>
						</div>
					</div>
			    </form>
			</div>
		</div>
    </div>
</div>
@endsection
