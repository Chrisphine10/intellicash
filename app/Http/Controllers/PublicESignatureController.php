<?php

namespace App\Http\Controllers;

use App\Models\ESignatureDocument;
use App\Models\ESignatureSignature;
use App\Models\ESignatureField;
use App\Models\ESignatureAuditTrail;
use App\Services\ESignatureService;
use App\Mail\ESignatureDocumentSigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PublicESignatureController extends Controller
{
    protected $eSignatureService;

    public function __construct(ESignatureService $eSignatureService)
    {
        $this->eSignatureService = $eSignatureService;
    }

    /**
     * Show the signing page
     */
    public function showSigningPage(Request $request, string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        // Check if signature is valid
        if (!$signature->canBeSigned()) {
            if ($signature->isExpired()) {
                return view('public.esignature.expired', compact('signature'));
            }
            
            if ($signature->isSigned()) {
                return view('public.esignature.already-signed', compact('signature'));
            }
            
            return view('public.esignature.invalid', compact('signature'));
        }

        // Mark as viewed
        $signature->markAsViewed();
        
        // Log audit trail
        ESignatureAuditTrail::logDocumentViewed($signature);

        $document = $signature->document;
        $fields = $document->fields()->assignedTo($signature->signer_email)->get();

        return view('public.esignature.sign', compact('signature', 'document', 'fields'));
    }

    /**
     * Process the signature submission
     */
    public function submitSignature(Request $request, string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        // Check if signature is valid
        if (!$signature->canBeSigned()) {
            return response()->json([
                'success' => false,
                'message' => 'This signature request is no longer valid.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'signature_data' => 'required|string',
            'signature_type' => 'required|string|in:drawn,typed,uploaded',
            'fields' => 'nullable|array',
            'fields.*.field_id' => 'required|integer',
            'fields.*.value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate and save field values
            $filledFields = [];
            if ($request->has('fields')) {
                foreach ($request->fields as $fieldData) {
                    $field = ESignatureField::find($fieldData['field_id']);
                    if ($field && $field->assigned_to === $signature->signer_email) {
                        if ($field->validateValue($fieldData['value'])) {
                            $field->update(['field_value' => $fieldData['value']]);
                            $filledFields[$field->field_name] = $fieldData['value'];
                        } else {
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid value for field: {$field->field_label}"
                            ], 422);
                        }
                    }
                }
            }

            // Prepare signature data
            $signatureData = [
                'signature' => $request->signature_data,
                'fields' => $filledFields,
                'metadata' => [
                    'signed_at' => now()->toISOString(),
                    'signature_type' => $request->signature_type,
                    'fields_count' => count($filledFields),
                ]
            ];

            // Mark signature as signed
            $signature->markAsSigned($signatureData, $request->signature_type);

            // Log audit trail
            ESignatureAuditTrail::logDocumentSigned($signature);

            // Check if document is fully signed
            $document = $signature->document;
            if ($document->isFullySigned()) {
                $document->update([
                    'status' => 'signed',
                    'completed_at' => now(),
                ]);

                // Send completion notification to sender
                Mail::to($document->sender_email)->send(new ESignatureDocumentSigned($document));
            }

            return response()->json([
                'success' => true,
                'message' => 'Document signed successfully!',
                'redirect_url' => route('esignature.public.success', $token)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process signature: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show success page
     */
    public function success(string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        if (!$signature->isSigned()) {
            return redirect()->route('esignature.public.sign', $token);
        }

        return view('public.esignature.success', compact('signature'));
    }

    /**
     * Decline signature
     */
    public function decline(Request $request, string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        if (!$signature->canBeSigned()) {
            return response()->json([
                'success' => false,
                'message' => 'This signature request is no longer valid.'
            ], 400);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $signature->markAsDeclined($request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Signature request declined.',
                'redirect_url' => route('esignature.public.declined', $token)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to decline signature: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show declined page
     */
    public function declined(string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        if ($signature->status !== 'declined') {
            return redirect()->route('esignature.public.sign', $token);
        }

        return view('public.esignature.declined', compact('signature'));
    }

    /**
     * Download the document for review
     */
    public function downloadDocument(string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        if (!$signature->canBeSigned()) {
            abort(403, 'Access denied');
        }

        $document = $signature->document;
        
        return response()->file(storage_path('app/public/' . $document->file_path), [
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"'
        ]);
    }

    /**
     * Get document fields for AJAX
     */
    public function getFields(string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        if (!$signature->canBeSigned()) {
            return response()->json(['error' => 'Invalid signature request'], 400);
        }

        $fields = $signature->document->fields()
            ->assignedTo($signature->signer_email)
            ->get()
            ->map(function ($field) {
                return [
                    'id' => $field->id,
                    'type' => $field->field_type,
                    'name' => $field->field_name,
                    'label' => $field->field_label,
                    'value' => $field->field_value,
                    'required' => $field->is_required,
                    'readonly' => $field->is_readonly,
                    'options' => $field->getFieldOptions(),
                    'position' => $field->getPosition(),
                    'page_number' => $field->page_number,
                ];
            });

        return response()->json(['fields' => $fields]);
    }

    /**
     * Validate field value
     */
    public function validateField(Request $request, string $token)
    {
        $signature = ESignatureSignature::where('signature_token', $token)->firstOrFail();
        
        if (!$signature->canBeSigned()) {
            return response()->json(['error' => 'Invalid signature request'], 400);
        }

        $request->validate([
            'field_id' => 'required|integer',
            'value' => 'required'
        ]);

        $field = ESignatureField::find($request->field_id);
        
        if (!$field || $field->assigned_to !== $signature->signer_email) {
            return response()->json(['error' => 'Field not found'], 404);
        }

        $isValid = $field->validateValue($request->value);
        
        return response()->json([
            'valid' => $isValid,
            'message' => $isValid ? 'Valid' : 'Invalid value for this field'
        ]);
    }
}
