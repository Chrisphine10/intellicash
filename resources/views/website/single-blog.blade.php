@extends('website.layouts')

@section('content')
<!-- Page Content-->
<section class="section single-blog">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="mb-5">
                    <h2 class="mb-2 title">{{ $post->translation->title }}</h2>
                    <span>{{ _lang('Posted on').' '.$post->created_at }}</span>
                </div>

                <div class="mb-5 text-center">
                    <img src="{{ asset('public/uploads/media/'.$post->image) }}" class="blog-image">
                </div>

                <div class="content">
                    {!! xss_clean($post->translation->content) !!}
                </div>

                <div class="comments">
                    @php $comments = $post->comments; @endphp
                    <h4 class="mb-4">Comments ({{ $comments->count() }})</h4>

                    @if(Session::has('success'))
                    <div class="alert alert-success">
                        <strong>{{ session('success') }}</strong>
                    </div>
                    @endif
                    
                    @if(Session::has('error'))
                    <div class="alert alert-danger">
                        <strong>{{ session('error') }}</strong>
                    </div>  
                    @endif

                    @if(Session::has('errors'))
                    @foreach ($errors->all() as $key => $error)
                    <div class="alert alert-danger">
                        <strong>{{ session('error') }}</strong>
                    </div>  
                    @endforeach
                    @endif

                    @foreach($comments as $comment)
                    <div class="single-comment {{ $comment->parent_id != null ? 'reply-comment' : '' }}">
                        <div class="d-flex">
                            <div class="comment_author_img">
                                <img src="{{ $comment->user_id == null ? asset('public/uploads/profile/default.png') : asset('public/uploads/profile/'. $comment->posted_by->profile_picture) }}" class="me-3 rounded-circle border" alt="author image">
                            </div>
                            <div class="comment-details w-100">
                                <div class="meta-info d-flex justify-content-between align-items-center mb-2">
                                    <h5>{{ $comment->name }}</h5>
                                    <span class="ms-3">{{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="d-block">{{ $comment->comment }}</p>
                                <a href="#" class="reply-btn">{{ _lang('Reply') }}</a>
                                
                                <div class="comment-form mt-4 reply-form">
                                    <h6 class="mt-0">{{ _lang('Reply Comment') }}</h6>
    
                                    <form method="post" autocomplete="off" action="{{ url('/post_comment') }}">
                                        @csrf
                                        <div class="row">
                                            <div class="col-lg-6 col-12">
                                                <div class="form-group">
                                                    <input name="name" type="text" value="{{ auth()->check() ? auth()->user()->name : old('name') }}" placeholder="{{ _lang('Your Name') }}" required>
                                                </div>
                                            </div>
                                            <div class="col-lg-6 col-12">
                                                <div class="form-group">
                                                    <input name="email" type="email" value="{{ auth()->check() ? auth()->user()->email : old('email') }}" placeholder="{{ _lang('Your Email') }}" required>
                                                </div>  
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <textarea name="comment" placeholder="{{ _lang('Comment') }}" required>{{ old('comment') }}</textarea>
                                                </div>
                                            </div>
                                            <input type="hidden" name="post_id" value="{{ $post->id }}">
                                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <button type="submit" class="send-btn">{{ _lang('Reply Comment') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>                                   
                    </div>
                    @endforeach
                    
                </div><!--End Comment List-->

                <div class="comment-form">
                    <h4>{{ _lang('Post Comment') }}</h4>

                    <form method="post" autocomplete="off" action="{{ url('/post_comment') }}">
                        @csrf
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <input type="text" placeholder="{{ _lang('Your Name') }}" name="name" value="{{ auth()->check() ? auth()->user()->name : old('name') }}" class="wow animate__zoomIn" data-wow-delay=".2s" required>
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <div class="form-group">
                                    <input type="email" placeholder="{{ _lang('Your Email') }}" name="email" value="{{ auth()->check() ? auth()->user()->email : old('email') }}" class="wow animate__zoomIn" data-wow-delay=".2s" required>
                                </div>  
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <textarea placeholder="{{ _lang('Comment') }}" name="comment" class="wow animate__zoomIn" data-wow-delay=".6s" required>{{ old('comment') }}</textarea>
                                </div>
                            </div>
                            <input type="hidden" name="post_id" value="{{ $post->id }}">
                            <div class="col-12">
                                <div class="form-group">
                                    <button type="submit" class="send-btn wow animate__zoomIn" data-wow-delay=".8s">{{ _lang('Post Comment') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection