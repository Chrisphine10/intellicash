<?php
namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $assets         = ['datatable'];
        $tenantId       = request()->tenant->id;
        $emailtemplates = EmailTemplate::where('template_type', 'tenant')
            ->whereNull('tenant_id')
            ->with(['tenantTemplate' => function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }])
            ->get()
            ->map(function ($template) {
                return (object) [
                    'id'                  => $template->tenantTemplate->id ?? $template->id,
                    'name'                => $template->tenantTemplate->name ?? $template->name,
                    'subject'             => $template->tenantTemplate->subject ?? $template->subject,
                    'email_body'          => $template->tenantTemplate->email_body ?? $template->email_body,
                    'sms_body'            => $template->tenantTemplate->sms_body ?? $template->sms_body,
                    'notification_body'   => $template->tenantTemplate->notification_body ?? $template->notification_body,
                    'tenant_id'           => $template->tenantTemplate->tenant_id ?? null,
                    'email_status'        => $template->tenantTemplate->email_status ?? 0,
                    'sms_status'          => $template->tenantTemplate->sms_status ?? 0,
                    'notification_status' => $template->tenantTemplate->notification_status ?? 0,
                    'template_mode'       => $template->tenantTemplate->template_mode ?? $template->template_mode,
                ];
            });

        return view('backend.admin.notification_template.list', compact('emailtemplates', 'assets'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $tenant, $id) {
        $assets        = ['tinymce'];
        $emailtemplate = EmailTemplate::whereRaw('(tenant_id = ? OR tenant_id IS NULL)', request()->tenant->id)
            ->where('id', $id)
            ->first();
        return view('backend.admin.notification_template.edit', compact('emailtemplate', 'id', 'assets'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $tenant, $id) {
        $validator = Validator::make($request->all(), [
            'name'              => 'required',
            'subject'           => 'required',
            'email_body'        => 'required_if:email_status,1',
            'sms_body'          => 'required_if:sms_status,1',
            'notification_body' => 'required_if:notification_status,1',
        ], [
            'email_body.required_if'        => _lang('Email body is required'),
            'sms_body.required_if'          => _lang('SMS text is required'),
            'notification_body.required_if' => _lang('Notification text is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('admin.notification_templates.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $defaultEmailtemplate = EmailTemplate::find($id);

        $emailtemplate = $defaultEmailtemplate;
        if ($defaultEmailtemplate->tenant_id != request()->tenant->id) {
            $emailtemplate            = $defaultEmailtemplate->replicate();
            $emailtemplate->tenant_id = request()->tenant->id;
        }

        $emailtemplate->name                = $request->input('name');
        $emailtemplate->subject             = $request->input('subject');
        $emailtemplate->email_body          = $request->input('email_body');
        $emailtemplate->sms_body            = $request->input('sms_body');
        $emailtemplate->notification_body   = $request->input('notification_body');
        $emailtemplate->email_status        = isset($request->email_status) ? $request->input('email_status') : 0;
        $emailtemplate->sms_status          = isset($request->sms_status) ? $request->input('sms_status') : 0;
        $emailtemplate->notification_status = isset($request->notification_status) ? $request->input('notification_status') : 0;

        $emailtemplate->save();
        
        return redirect()->route('email_templates.index')->with('success', _lang('Updated successfully'));

    }
}
