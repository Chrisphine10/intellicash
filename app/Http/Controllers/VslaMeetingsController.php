<?php

namespace App\Http\Controllers;

use App\Models\VslaMeeting;
use App\Models\VslaMeetingAttendance;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class VslaMeetingsController extends Controller
{
    /**
     * Display a listing of VSLA meetings
     */
    public function index($tenant)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        try {
            $meetings = $tenant->vslaMeetings()
                ->with(['createdUser', 'attendance.member'])
                ->orderBy('meeting_date', 'desc')
                ->orderBy('meeting_time', 'desc')
                ->paginate(20);
            
            return view('backend.admin.vsla.meetings.index', compact('meetings'));
        } catch (\Exception $e) {
            \Log::error('VSLA Meetings Index Error: ' . $e->getMessage());
            return back()->with('error', _lang('An error occurred while loading meetings. Please try again.'));
        }
    }

    /**
     * Show the form for creating a new meeting
     */
    public function create($tenant)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $members = Member::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();
        
        return view('backend.admin.vsla.meetings.create', compact('members'));
    }

    /**
     * Store a newly created meeting
     */
    public function store(Request $request, $tenant)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $validator = Validator::make($request->all(), [
            'meeting_date' => 'required|date|after_or_equal:today',
            'meeting_time' => 'required|date_format:H:i',
            'agenda' => 'nullable|string|max:1000',
            'attendance' => 'required|array',
            'attendance.*.member_id' => 'required|exists:members,id',
            'attendance.*.present' => 'boolean',
            'attendance.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();

        try {
            // Generate meeting number
            $meetingNumber = 'VSLA-' . date('Y') . '-' . str_pad($tenant->vslaMeetings()->count() + 1, 4, '0', STR_PAD_LEFT);
            
            $meeting = VslaMeeting::create([
                'tenant_id' => $tenant->id,
                'meeting_number' => $meetingNumber,
                'meeting_date' => $request->meeting_date,
                'meeting_time' => $request->meeting_time,
                'agenda' => $request->agenda,
                'status' => 'scheduled',
                'created_user_id' => auth()->id(),
            ]);

            // Create attendance records
            foreach ($request->attendance as $attendanceData) {
                VslaMeetingAttendance::create([
                    'meeting_id' => $meeting->id,
                    'member_id' => $attendanceData['member_id'],
                    'present' => $attendanceData['present'] ?? false,
                    'notes' => $attendanceData['notes'] ?? null,
                ]);
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang('Meeting created successfully'), 'data' => $meeting]);
            }

            return redirect()->route('vsla.meetings.index')->with('success', _lang('Meeting created successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while creating the meeting')]);
            }
            
            return back()->with('error', _lang('An error occurred while creating the meeting'))->withInput();
        }
    }

    /**
     * Display the specified meeting
     */
    public function show($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        try {
            $meeting = $tenant->vslaMeetings()
                ->with(['createdUser', 'attendance.member', 'transactions.member'])
                ->findOrFail($id);
            
            return view('backend.admin.vsla.meetings.show', compact('meeting'));
        } catch (\Exception $e) {
            \Log::error('VSLA Meeting Show Error: ' . $e->getMessage());
            return back()->with('error', _lang('An error occurred while loading the meeting. Please try again.'));
        }
    }

    /**
     * Show the form for editing the specified meeting
     */
    public function edit($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $meeting = $tenant->vslaMeetings()->findOrFail($id);
        $members = Member::where('tenant_id', $tenant->id)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();
        
        return view('backend.admin.vsla.meetings.edit', compact('meeting', 'members'));
    }

    /**
     * Update the specified meeting
     */
    public function update(Request $request, $tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $meeting = $tenant->vslaMeetings()->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'meeting_date' => 'required|date',
            'meeting_time' => 'required|date_format:H:i',
            'agenda' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        $meeting->update($request->all());

        if ($request->ajax()) {
            return response()->json(['result' => 'success', 'message' => _lang('Meeting updated successfully')]);
        }

        return redirect()->route('vsla.meetings.index')->with('success', _lang('Meeting updated successfully'));
    }

    /**
     * Record attendance for a meeting
     */
    public function recordAttendance(Request $request, $tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $meeting = $tenant->vslaMeetings()->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'attendance' => 'required|array',
            'attendance.*.member_id' => 'required|exists:members,id',
            'attendance.*.present' => 'boolean',
            'attendance.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return back()->withErrors($validator)->withInput();
            }
        }

        DB::beginTransaction();

        try {
            // Delete existing attendance records
            $meeting->attendance()->delete();

            // Create new attendance records
            foreach ($request->attendance as $attendanceData) {
                VslaMeetingAttendance::create([
                    'meeting_id' => $meeting->id,
                    'member_id' => $attendanceData['member_id'],
                    'present' => $attendanceData['present'] ?? false,
                    'notes' => $attendanceData['notes'] ?? null,
                ]);
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json(['result' => 'success', 'message' => _lang('Attendance recorded successfully')]);
            }

            return back()->with('success', _lang('Attendance recorded successfully'));

        } catch (\Exception $e) {
            DB::rollback();
            
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('An error occurred while recording attendance')]);
            }
            
            return back()->with('error', _lang('An error occurred while recording attendance'))->withInput();
        }
    }

    /**
     * Remove the specified meeting
     */
    public function destroy($tenant, $id)
    {
        // Check permission - admin has full access
        if (!is_admin()) {
            return back()->with('error', _lang('Permission denied!'));
        }
        
        $tenant = app('tenant');
        
        if (!$tenant->isVslaEnabled()) {
            return redirect()->route('modules.index')->with('error', _lang('VSLA module is not enabled'));
        }
        
        $meeting = $tenant->vslaMeetings()->findOrFail($id);
        
        // Check if meeting has transactions
        if ($meeting->transactions()->count() > 0) {
            return back()->with('error', _lang('Cannot delete meeting with existing transactions'));
        }
        
        $meeting->delete();

        if (request()->ajax()) {
            return response()->json(['result' => 'success', 'message' => _lang('Meeting deleted successfully')]);
        }

        return redirect()->route('vsla.meetings.index')->with('success', _lang('Meeting deleted successfully'));
    }
}
