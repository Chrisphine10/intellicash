<?php

namespace App\Http\Controllers\SuperAdmin;

use DataTables;
use Illuminate\Http\Request;
use App\Models\ContactMessage;
use App\Http\Controllers\Controller;

class ContactController extends Controller {

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
        return view('backend.super_admin.contact_message.list', compact('assets'));
    }

    public function get_table_data() {
        $contactmessages = ContactMessage::select('contact_messages.*')
            ->orderBy("contact_messages.id", "desc");

        return Datatables::eloquent($contactmessages)
            ->addColumn('status', function ($contactmessage) {
                return $contactmessage->status == 0 ? show_status(_lang('Unread'), 'danger') : show_status(_lang('Read'), 'success');
            })
            ->addColumn('action', function ($contactmessage) {
                return '<div class="text-center">'
                . '<form action="' . route('admin.contact_messages.destroy', $contactmessage['id']) . '" method="post">'
                . '<a class="btn btn-primary btn-xs ajax-modal" href="' . route('admin.contact_messages.show', $contactmessage['id']) . '" data-title="' . _lang('Message Details') . '"><i class="fas fa-eye"></i> ' . _lang('Details') . '</a>'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '&nbsp;<button class="btn btn-danger btn-xs btn-remove" type="submit"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>';
            })
            ->setRowId(function ($contactmessage) {
                return "row_" . $contactmessage->id;
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $contactmessage = ContactMessage::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            $contactmessage->status = 1;
            $contactmessage->save();
            return view('backend.super_admin.contact_message.modal.view', compact('contactmessage', 'id'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $contactmessage = ContactMessage::find($id);
        $contactmessage->delete();
        return redirect()->route('admin.contact_messages.index')->with('success', _lang('Deleted Successfully'));
    }
}