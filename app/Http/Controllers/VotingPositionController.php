<?php

namespace App\Http\Controllers;

use App\Models\VotingPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VotingPositionController extends Controller
{
    /**
     * Display a listing of voting positions
     */
    public function index()
    {
        $tenantId = app('tenant')->id ?? auth()->user()->tenant_id;
        $positions = VotingPosition::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(20);

        return view('backend.voting.positions.index', compact('positions'));
    }

    /**
     * Show the form for creating a new voting position
     */
    public function create()
    {
        return view('backend.voting.positions.create');
    }

    /**
     * Store a newly created voting position
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_winners' => 'required|integer|min:1',
        ]);

        VotingPosition::create([
            'name' => $request->name,
            'description' => $request->description,
            'max_winners' => $request->max_winners,
            'tenant_id' => app('tenant')->id ?? auth()->user()->tenant_id,
        ]);

        return redirect()->route('voting.positions.index')
            ->with('success', _lang('Voting position created successfully'));
    }

    /**
     * Show the form for editing the specified voting position
     */
    public function edit($tenant, $id)
    {
        $tenantId = app('tenant')->id ?? auth()->user()->tenant_id;
        $position = VotingPosition::where('tenant_id', $tenantId)->findOrFail($id);
        
        return view('backend.voting.positions.edit', compact('position'));
    }

    /**
     * Update the specified voting position
     */
    public function update(Request $request, $tenant, $id)
    {
        $tenantId = app('tenant')->id ?? auth()->user()->tenant_id;
        $position = VotingPosition::where('tenant_id', $tenantId)->findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'max_winners' => 'required|integer|min:1',
        ]);

        $position->update([
            'name' => $request->name,
            'description' => $request->description,
            'max_winners' => $request->max_winners,
        ]);

        return redirect()->route('voting.positions.index')
            ->with('success', _lang('Voting position updated successfully'));
    }

    /**
     * Toggle the active status of the voting position
     */
    public function toggleActive($tenant, $id)
    {
        $tenantId = app('tenant')->id ?? auth()->user()->tenant_id;
        $position = VotingPosition::where('tenant_id', $tenantId)->findOrFail($id);
        
        $position->update(['is_active' => !$position->is_active]);

        $status = $position->is_active ? 'activated' : 'deactivated';
        
        return redirect()->back()
            ->with('success', _lang("Voting position {$status} successfully"));
    }

    /**
     * Remove the specified voting position
     */
    public function destroy($tenant, $id)
    {
        $tenantId = app('tenant')->id ?? auth()->user()->tenant_id;
        $position = VotingPosition::where('tenant_id', $tenantId)->findOrFail($id);
        
        // Check if position has active elections
        if ($position->elections()->where('status', '!=', 'closed')->exists()) {
            return redirect()->back()
                ->with('error', _lang('Cannot delete position with active elections'));
        }

        $position->delete();

        return redirect()->route('voting.positions.index')
            ->with('success', _lang('Voting position deleted successfully'));
    }
}
