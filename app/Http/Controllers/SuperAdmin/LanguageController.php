<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use App\Utilities\Translator;
use App\Http\Controllers\Controller;

class LanguageController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        return view('backend.super_admin.administration.language.list');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        $alert_col = 'col-lg-8 offset-lg-2';
        return view('backend.super_admin.administration.language.create', compact('alert_col'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $this->validate($request, [
            'language_name' => 'required|alpha|string|max:30',
            'country_flag'  => 'required',
        ]);

        $name         = $request->language_name;
        $country_flag = strtolower($request->country_flag);

        if (file_exists(resource_path() . "/language/$name---$country_flag.php")) {
            return redirect()->back()->with('error', _lang('Language already exists !'));
        }

        $language = file_get_contents(resource_path() . "/language/language.php");
        $new_file = fopen(resource_path() . "/language/$name---$country_flag.php", 'w+');
        fwrite($new_file, $language);
        fclose($new_file);

        return redirect()->route('admin.languages.index')->with('success', _lang('Language Created successfully'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $name
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        if (file_exists(resource_path() . "/language/$id.php")) {
            $title = _lang('Update All Translation');
            require resource_path() . "/language/$id.php";

            //Find New Language key
            $language_2 = Translator::get_language_key();
            $new_keys   = array_diff_key($language_2, $language);

            $language = array_merge($language, $new_keys);

            return view('backend.super_admin.administration.language.edit', compact('language', 'id', 'title'));
        }
    }

    /**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $name
	 * @return \Illuminate\Http\Response
	 */
	public function edit_website_language($id) {
		if (file_exists(resource_path() . "/language/$id.php")) {
			$title = _lang('Update Website Translation');
			require resource_path() . "/language/$id.php";

			//Find New Language key
			$websiteLanguage = Translator::get_website_language_key();
			$match_keys = array_intersect_key($language, $websiteLanguage);
			$new_keys = array_diff_key($websiteLanguage, $match_keys);

			$language = array_merge($match_keys, $new_keys);

			return view('backend.super_admin.administration.language.edit', compact('language', 'id', 'title'));
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
		@ini_set('max_execution_time', 0);
		@set_time_limit(0);

		require resource_path() . "/language/$id.php";

		//Find New Language key
		$newLanguage = [];
		foreach ($_POST['language'] as $key => $value) {
			$newLanguage[str_replace("_", " ", $key)] = $value;
		}

		$new_keys = array_diff_key($newLanguage, $language);

		$language = array_merge($language, $new_keys);

		foreach ($_POST['language'] as $key => $value) {
			$language[str_replace("_", " ", $key)] = $value;
		}

		$contents = "<?php \n\n";
		$contents .= '$language=array();' . "\n\n";
		foreach ($language as $key => $value) {
			$l_value = str_replace('"', '', $value);
			$contents .= '$language["' . str_replace("_", " ", $key) . '"]="' . $l_value . '";' . "\n";
		}

		$file = fopen(resource_path() . "/language/$id.php", "w");

		if (fwrite($file, $contents)) {
			if ($request->ajax()) {
				return response()->json(['result' => 'success', 'message' => _lang('Updated successfully')]);
			} else {
				return redirect()->route('admin.languages.index')->with('success', _lang('Updated successfully'));
			}
		} else {
			if ($request->ajax()) {
				return response()->json(['result' => 'error', 'message' => _lang('Update failed !')]);
			} else {
				return redirect()->route('admin.languages.index')->with('success', _lang('Update failed !'));
			}
		}
	}

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        if (file_exists(resource_path() . "/language/$id.php")) {
            unlink(resource_path() . "/language/$id.php");
            return redirect()->route('admin.languages.index')->with('success', _lang('Removed successfully'));
        }
    }
}
