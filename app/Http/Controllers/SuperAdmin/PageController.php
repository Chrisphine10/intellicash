<?php

namespace App\Http\Controllers\SuperAdmin;

use DB;
use Carbon\Carbon;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\SettingTranslation;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class PageController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_timezone());
    }

    /**
     * Display Default Pages
     *
     * @return \Illuminate\Http\Response
     */
    public function default_pages($slug = '') {
        $data              = array();
        $data['assets']    = ['tinymce'];
        $data['alert_col'] = 'col-lg-8 offset-lg-2';
        if ($slug != '') {
            $data['pageData']  = json_decode(get_trans_option($slug . '_page'));
            $data['pageMedia'] = json_decode(get_trans_option($slug . '_page_media'));

            return view("backend.super_admin.website_management.page.default.$slug", $data);
        }
        return view('backend.super_admin.website_management.page.default-list');
    }

    public function store_default_pages(Request $request) {
        foreach ($_POST as $key => $value) {
            if ($key == "_token" || $key == "model_language") {continue;}

            $data               = array();
            $data['value']      = is_array($value) ? json_encode($value) : $value;
            $data['updated_at'] = Carbon::now();

            if (Setting::where('name', $key)->exists()) {
                $setting        = Setting::where('name', $key)->first();
                $setting->value = $data['value'];
                $setting->save();

                //Update Translation
                $translation = SettingTranslation::firstOrNew([
                    'setting_id' => $setting->id,
                    'locale'     => get_language(),
                ]);

                $translation->setting_id = $setting->id;
                $translation->value      = $setting->value;
                $translation->save();

            } else {
                $setting        = new Setting();
                $setting->name  = $key;
                $setting->value = $data['value'];
                $setting->save();

                //Save Translation
                $translation = new SettingTranslation(['value' => $data['value']]);
                $setting->translation()->save($translation);
            }

        }

        //Upload File
        foreach ($_FILES as $key => $value) {
            $this->upload_file($key, $request);
        }

        return back()->with('success', _lang('Saved Sucessfully'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $assets = ['datatable'];
        $pages  = Page::all()->sortByDesc("id");
        return view('backend.super_admin.website_management.page.list', compact('pages', 'assets'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        $assets = ['tinymce'];
        return view('backend.super_admin.website_management.page.create', compact('assets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'trans.title' => 'required',
            'trans.body'  => 'required',
            'status'      => 'required',
        ], [
            'trans.title.required' => _lang('Title is required'),
            'trans.body.required'  => _lang('Body is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.pages.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $page         = new Page();
        $page->slug   = $request->trans['title'];
        $page->status = $request->input('status');

        $page->save();

        if (!$request->ajax()) {
            return redirect()->route('admin.pages.index')->with('success', _lang('Saved Sucessfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Sucessfully'), 'data' => $page, 'table' => '#pages_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $page   = Page::find($id);
        $assets = ['tinymce'];
        return view('backend.super_admin.website_management.page.edit', compact('page', 'id', 'assets'));
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
            'trans.title' => 'required',
            'trans.body'  => 'required',
            'status'      => 'required',
        ], [
            'trans.title.required' => _lang('Title is required'),
            'trans.body.required'  => _lang('Body is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.pages.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        DB::beginTransaction();

        $page         = Page::find($id);
        $page->status = $request->input('status');

        $page->save();

        DB::commit();

        if (!$request->ajax()) {
            return redirect()->route('admin.pages.index')->with('success', _lang('Updated Sucessfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Sucessfully'), 'data' => $page, 'table' => '#pages_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $page = Page::find($id);
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', _lang('Deleted Sucessfully'));
    }

    private function upload_file($file_name, Request $request) {
        if ($request->hasFile($file_name)) {
            $files = $request->file($file_name);
            //dd(array_keys($file)[0]);
            foreach ($files as $key => $file) {
                $name            = 'file_' . rand() . time() . "." . $file->getClientOriginalExtension();
                $destinationPath = public_path('/uploads/media');
                $file->move($destinationPath, $name);

                $data               = array();
                $data['updated_at'] = Carbon::now();

                $existingSettings = Setting::where('name', $file_name)->first();
                if ($existingSettings) {
                    $existingValue       = json_decode($existingSettings->value, true);
                    $existingValue[$key] = $name;
                    $data['value']       = $existingValue;
                    Setting::where('name', '=', $file_name)->update($data);
                } else {
                    $data['name']       = $file_name;
                    $data['value']      = json_encode(array($key => $name));
                    $data['created_at'] = Carbon::now();
                    Setting::insert($data);
                }
            }
        }
    }

}