<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class TestimonialController extends Controller {

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
        $assets       = ['datatable'];
        $testimonials = Testimonial::all()->sortByDesc("id");
        return view('backend.super_admin.website_management.testimonial.list', compact('testimonials', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.super_admin.website_management.testimonial.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'trans.name'        => 'required',
            'trans.testimonial' => 'required',
            'image'             => 'nullable|image|max:4096',
        ], [
            'trans.name.required'        => _lang('Customer Name is required'),
            'trans.testimonial.required' => _lang('Testimonial is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.testimonials.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $image = 'default/client-placeholder.png';
        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $testimonial        = new Testimonial();
        $testimonial->image = $image;
        $testimonial->save();

        $testimonial->name        = $testimonial->translation->name;
        $testimonial->testimonial = $testimonial->translation->testimonial;
        $testimonial->image       = '<img src="' . asset('public/uploads/media/' . $testimonial->image) . '" class="thumb-sm img-thumbnail"/>';

        if (!$request->ajax()) {
            return redirect()->route('admin.testimonials.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $testimonial, 'table' => '#testimonials_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $testimonial = Testimonial::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.super_admin.website_management.testimonial.modal.edit', compact('testimonial', 'id'));
        }

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
            'trans.name'        => 'required',
            'trans.testimonial' => 'required',
            'image'             => 'nullable|image|max:4096',
        ], [
            'trans.name.required'        => _lang('Customer Name is required'),
            'trans.testimonial.required' => _lang('Testimonial is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.testimonials.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $testimonial = Testimonial::find($id);
        if ($request->hasfile('image')) {
            $testimonial->image = $image;
        }
        $testimonial->save();

        $testimonial->name        = $testimonial->translation->name;
        $testimonial->testimonial = $testimonial->translation->testimonial;
        $testimonial->image       = '<img src="' . asset('public/uploads/media/' . $testimonial->image) . '" class="thumb-sm img-thumbnail"/>';

        if (!$request->ajax()) {
            return redirect()->route('admin.testimonials.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $testimonial, 'table' => '#testimonials_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $testimonial = Testimonial::find($id);
        $testimonial->delete();
        return redirect()->route('admin.testimonials.index')->with('success', _lang('Deleted Successfully'));
    }
}