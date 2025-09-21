<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $assets = ['datatable'];
        $posts  = Post::all()->sortByDesc("id");
        return view('backend.super_admin.website_management.blog.list', compact('posts', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $assets = ['tinymce'];
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('backend.super_admin.website_management.blog.create', compact('alert_col', 'assets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'trans.title'             => 'required',
            'trans.short_description' => 'required',
            'image'                   => 'nullable|image',
            'status'                  => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.posts.create')
                ->withErrors($validator)
                ->withInput();
        }

        $image = "default.png";
        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $post                  = new Post();
        $post->image           = $image;
        $post->slug            = $request->trans['title'];
        $post->status          = $request->input('status');
        $post->created_user_id = auth()->id();

        $post->save();

        return redirect()->route('admin.posts.index')->with('success', _lang('Saved Successfully'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $alert_col = 'col-lg-8 offset-lg-2';
        $assets = ['tinymce'];
        $post = Post::find($id);
        return view('backend.super_admin.website_management.blog.edit', compact('post', 'id', 'alert_col', 'assets'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'trans.title'             => 'required',
            'trans.short_description' => 'required',
            'image'                   => 'nullable|image',
            'status'                  => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.posts.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $post = Post::find($id);
        if ($request->hasfile('image')) {
            $post->image = $image;
        }
        $post->status = $request->input('status');

        $post->save();

        return redirect()->route('admin.posts.index')->with('success', _lang('Updated Successfully'));

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $news = Post::find($id);
        $news->delete();
        return redirect()->route('admin.posts.index')->with('success', _lang('Deleted Successfully'));
    }
}