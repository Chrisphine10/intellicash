<?php
namespace App\Http\Controllers\SuperAdmin;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller {

    public function index() {
        $assets      = ['datatable'];
        $backupFiles = Storage::disk('local')->files('backups');
        return view('backend.super_admin.administration.backup.list', compact('assets', 'backupFiles'));
    }

    public function create_backup(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $backupData = [];

        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $table) {
            $tableName              = reset($table); // Get table name
            $tableData              = DB::table($tableName)->get()->toArray();
            if($tableName == 'users'){
                foreach ($tableData as $key => $value) {
                    unset($tableData[$key]->email_tenant);
                }
            }
            $backupData[$tableName] = $tableData;
        }

        $jsonData = json_encode($backupData, JSON_PRETTY_PRINT);

        $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.json';
        Storage::disk('local')->put('backups/' . $fileName, $jsonData);

        return back()->with('success', _lang('New backup created successfully'));
    }

    public function show_restore_form() {
        $alert_col   = 'col-lg-4 offset-lg-4';
        $backupFiles = Storage::disk('local')->files('backups');
        return view('backend.super_admin.administration.backup.restore', compact('backupFiles', 'alert_col'));
    }

    public function restore_backup(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $backupData = null;

        if ($request->hasFile('upload_file')) {
            $uploadedFile = $request->file('upload_file');

            if ($uploadedFile->getClientOriginalExtension() !== 'json') {
                return redirect()->back()->with('error', _lang('Invalid file type. Please upload a valid JSON file.'));
            }

            $backupData = json_decode(file_get_contents($uploadedFile->getRealPath()), true);
        } else {
            $fileName = $request->input('backup_file');

            if (! Storage::disk('local')->exists($fileName)) {
                return redirect()->back()->with('error', _lang('Backup file not found') . ': ' . $fileName);
            }

            $jsonData   = Storage::disk('local')->get($fileName);
            $backupData = json_decode($jsonData, true);
        }

        if ($backupData) {
            try {
                DB::beginTransaction();
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                foreach ($backupData as $tableName => $tableData) {
                    DB::table($tableName)->delete();
                    DB::table($tableName)->insert($tableData);
                }

                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', _lang('Database restored successfully'));
        }

        return redirect()->back()->with('error', _lang('No backup data found.'));
    }

    public function download($file) {

        if (! Storage::disk('local')->exists('backups/' . $file)) {
            return redirect()->back()->with('error', _lang('Backup file not found') . ': ' . $file);
        }

        $filePath = storage_path('app/private/backups/' . $file);

        return response()->download($filePath, $file);
    }

    public function destroy($file) {
        if (! Storage::disk('local')->exists('backups/' . $file)) {
            return redirect()->back()->with('error', _lang('Backup file not found') . ': ' . $file);
        }

        Storage::disk('local')->delete('backups/' . $file);

        return redirect()->back()->with('success', _lang('Backup deleted successfully') . ': ' . $file);
    }
}
