<?php

namespace App\Http\Controllers\SuperAdmin;

use Validator;
use App\Models\Team;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TeamController extends Controller {

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
        $teams  = Team::all()->sortByDesc("id");
        return view('backend.super_admin.website_management.team.list', compact('teams', 'assets'));
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
            return view('backend.super_admin.website_management.team.modal.create');
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
            'trans.name' => 'required',
            'image'      => 'nullable|image|max:4096',
            'trans.role' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.teams.create')
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

        $team        = new Team();
        $team->image = $image;
        $team->save();


        if (!$request->ajax()) {
            return redirect()->route('admin.teams.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $team, 'table' => '#teams_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $team = Team::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.super_admin.website_management.team.modal.edit', compact('team', 'id'));
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
            'trans.name' => 'required',
            'image'      => 'nullable|image|max:4096',
            'trans.role' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.teams.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        $team       = Team::find($id);
        if ($request->hasfile('image')) {
            $team->image = $image;
        }
        $team->save();

        if (!$request->ajax()) {
            return redirect()->route('admin.teams.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $team, 'table' => '#teams_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $team = Team::find($id);
        $team->delete();
        return redirect()->route('admin.teams.index')->with('success', _lang('Deleted Successfully'));
    }
}
