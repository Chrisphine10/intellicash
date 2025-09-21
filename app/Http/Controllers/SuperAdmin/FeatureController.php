<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Feature;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FeatureController extends Controller {

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
        $assets   = ['datatable'];
        $features = Feature::all()->sortByDesc("id");
        return view('backend.super_admin.website_management.feature.list', compact('features', 'assets'));
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
            return view('backend.super_admin.website_management.feature.modal.create');
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
            'icon'          => 'required',
            'trans.title'   => 'required',
            'trans.content' => 'required',
        ], [
            'trans.title.required'   => _lang('Title is required'),
            'trans.content.required' => _lang('Content is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.features.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $service       = new Feature();
        $service->icon = $request->input('icon');

        $service->save();

        $service->title = $service->translation->title;
        $service->body  = $service->translation->body;

        if (!$request->ajax()) {
            return redirect()->route('admin.features.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $service, 'table' => '#features_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $feature = Feature::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.super_admin.website_management.feature.modal.edit', compact('feature', 'id'));
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
            'icon'          => 'required',
            'trans.title'   => 'required',
            'trans.content' => 'required',
        ], [
            'trans.title.required'   => _lang('Title is required'),
            'trans.content.required' => _lang('Content is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.features.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $service       = Feature::find($id);
        $service->icon = $request->input('icon');

        $service->save();

        $service->title = $service->translation->title;
        $service->body  = $service->translation->body;

        if (!$request->ajax()) {
            return redirect()->route('admin.features.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $service, 'table' => '#features_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $service = Feature::find($id);
        $service->delete();
        return redirect()->route('admin.features.index')->with('success', _lang('Deleted Successfully'));
    }
}