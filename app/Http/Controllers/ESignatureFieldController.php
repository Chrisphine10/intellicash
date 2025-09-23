<?php

namespace App\Http\Controllers;

use App\Models\ESignatureField;
use App\Models\ESignatureDocument;
use Illuminate\Http\Request;

class ESignatureFieldController extends Controller
{
    /**
     * Display a listing of fields for a document
     */
    public function index(ESignatureDocument $document)
    {
        $this->authorize('view', $document);
        
        $fields = $document->fields()->orderBy('page_number')->orderBy('position_y')->get();
        
        return response()->json($fields);
    }

    /**
     * Store a newly created field
     */
    public function store(Request $request, ESignatureDocument $document)
    {
        $this->authorize('update', $document);

        $request->validate([
            'field_type' => 'required|string|in:text,textarea,email,phone,date,checkbox,radio,dropdown,signature',
            'field_name' => 'required|string|max:255',
            'field_label' => 'required|string|max:255',
            'field_value' => 'nullable|string',
            'field_options' => 'nullable|array',
            'is_required' => 'boolean',
            'is_readonly' => 'boolean',
            'position_x' => 'nullable|integer|min:0',
            'position_y' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:1',
            'height' => 'nullable|integer|min:1',
            'page_number' => 'required|integer|min:1',
            'assigned_to' => 'nullable|email',
        ]);

        try {
            $field = ESignatureField::create([
                'document_id' => $document->id,
                'tenant_id' => request()->tenant->id,
                'field_type' => $request->field_type,
                'field_name' => $request->field_name,
                'field_label' => $request->field_label,
                'field_value' => $request->field_value,
                'field_options' => $request->field_options,
                'is_required' => $request->is_required ?? false,
                'is_readonly' => $request->is_readonly ?? false,
                'position_x' => $request->position_x ?? 0,
                'position_y' => $request->position_y ?? 0,
                'width' => $request->width ?? 200,
                'height' => $request->height ?? 50,
                'page_number' => $request->page_number,
                'assigned_to' => $request->assigned_to,
            ]);

            return response()->json([
                'success' => true,
                'field' => $field,
                'message' => 'Field created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create field: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified field
     */
    public function update(Request $request, ESignatureField $field)
    {
        $this->authorize('update', $field->document);

        $request->validate([
            'field_type' => 'sometimes|string|in:text,textarea,email,phone,date,checkbox,radio,dropdown,signature',
            'field_name' => 'sometimes|string|max:255',
            'field_label' => 'sometimes|string|max:255',
            'field_value' => 'nullable|string',
            'field_options' => 'nullable|array',
            'is_required' => 'boolean',
            'is_readonly' => 'boolean',
            'position_x' => 'nullable|integer|min:0',
            'position_y' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:1',
            'height' => 'nullable|integer|min:1',
            'page_number' => 'sometimes|integer|min:1',
            'assigned_to' => 'nullable|email',
        ]);

        try {
            $field->update($request->only([
                'field_type', 'field_name', 'field_label', 'field_value',
                'field_options', 'is_required', 'is_readonly', 'position_x',
                'position_y', 'width', 'height', 'page_number', 'assigned_to'
            ]));

            return response()->json([
                'success' => true,
                'field' => $field,
                'message' => 'Field updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update field: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified field
     */
    public function destroy(ESignatureField $field)
    {
        $this->authorize('update', $field->document);

        try {
            $field->delete();

            return response()->json([
                'success' => true,
                'message' => 'Field deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete field: ' . $e->getMessage()
            ], 500);
        }
    }
}
