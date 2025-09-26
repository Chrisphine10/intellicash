<?php

namespace App\Http\Controllers;

use App\Models\ESignatureDocument;
use App\Models\ESignatureSignature;
use App\Models\ESignatureField;
use App\Models\ESignatureAuditTrail;
use App\Models\User;
use App\Services\ESignatureService;
use App\Services\ESignatureSecurityService;
use App\Services\ESignatureFileSecurityService;
use App\Mail\ESignatureDocumentSent;
use App\Mail\ESignatureDocumentSigned;
use App\Notifications\ESignatureDocumentSentNotification;
use App\Notifications\ESignatureDocumentSignedNotification;
use App\Notifications\ESignatureDocumentCompletedNotification;
use App\Notifications\ESignatureDocumentExpiredNotification;
use App\Notifications\ESignatureDocumentCancelledNotification;
use App\Notifications\ESignatureReminderNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ESignatureController extends Controller
{
    protected $eSignatureService;
    protected $securityService;
    protected $fileSecurityService;

    public function __construct(
        ESignatureService $eSignatureService,
        ESignatureSecurityService $securityService,
        ESignatureFileSecurityService $fileSecurityService
    ) {
        $this->eSignatureService = $eSignatureService;
        $this->securityService = $securityService;
        $this->fileSecurityService = $fileSecurityService;
    }

    /**
     * Display a listing of documents
     */
    public function index(Request $request)
    {
        $query = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->with(['creator', 'signatures']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by document type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('document_type', $request->type);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
                  ->orWhere('sender_name', 'like', '%' . $request->search . '%');
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get statistics for all documents (not filtered)
        $tenantId = request()->tenant->id;
        $stats = [
            'total' => ESignatureDocument::where('tenant_id', $tenantId)->count(),
            'draft' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'sent' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
            'signed' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'signed')->count(),
            'expired' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'expired')->count(),
            'cancelled' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'cancelled')->count(),
        ];

        return view('backend.esignature.documents.index', compact('documents', 'stats'));
    }

    /**
     * Show the form for creating a new document
     */
    public function create()
    {
        return view('backend.esignature.documents.create');
    }

    /**
     * Store a newly created document with enhanced security
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'document_type' => 'required|string|in:contract,agreement,form,policy,other',
            'document_file' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'custom_message' => 'nullable|string|max:1000',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_company' => 'nullable|string|max:255',
            'signers' => 'required|array|min:1|max:10', // Limit to 10 signers
            'signers.*.email' => 'required|email|max:255',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.phone' => 'nullable|string|max:20',
            'signers.*.company' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now|before:+1 year', // Max 1 year expiry
        ]);

        try {
            // Enhanced file security validation
            $file = $request->file('document_file');
            $fileErrors = $this->fileSecurityService->validateAndSecureFile($file);
            
            if (!empty($fileErrors)) {
                return back()->withInput()->with('error', implode(', ', $fileErrors));
            }

            // Generate secure file path
            $secureFilePath = $this->fileSecurityService->storeFileSecurely($file, request()->tenant->id);
            
            // Generate document integrity hash
            $documentHash = $this->fileSecurityService->generateDocumentHash($secureFilePath);

            // Sanitize input data
            $sanitizedData = [
                'title' => $this->securityService->sanitizeInput($request->title),
                'description' => $request->description ? $this->securityService->sanitizeInput($request->description) : null,
                'custom_message' => $request->custom_message ? $this->securityService->sanitizeInput($request->custom_message) : null,
                'sender_name' => $request->sender_name ? $this->securityService->sanitizeInput($request->sender_name) : auth()->user()->name,
                'sender_email' => $request->sender_email ?: auth()->user()->email,
                'sender_company' => $request->sender_company ? $this->securityService->sanitizeInput($request->sender_company) : null,
            ];

            // Sanitize signer data
            $sanitizedSigners = [];
            foreach ($request->signers as $signer) {
                $sanitizedSigners[] = [
                    'email' => $signer['email'],
                    'name' => $this->securityService->sanitizeInput($signer['name']),
                    'phone' => $signer['phone'] ? $this->securityService->sanitizeInput($signer['phone']) : null,
                    'company' => $signer['company'] ? $this->securityService->sanitizeInput($signer['company']) : null,
                ];
            }

            // Create document with enhanced security
            $document = ESignatureDocument::create([
                'tenant_id' => request()->tenant->id,
                'title' => $sanitizedData['title'],
                'description' => $sanitizedData['description'],
                'document_type' => $request->document_type,
                'file_path' => $secureFilePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'status' => 'draft',
                'custom_message' => $sanitizedData['custom_message'],
                'sender_name' => $sanitizedData['sender_name'],
                'sender_email' => $sanitizedData['sender_email'],
                'sender_company' => $sanitizedData['sender_company'],
                'signers' => $sanitizedSigners,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null,
                'created_by' => auth()->id(),
                'document_hash' => $documentHash, // Store integrity hash
            ]);

            // Log audit trail
            ESignatureAuditTrail::logDocumentCreated($document, auth()->user());

            Log::info('E-signature document created', [
                'document_id' => $document->id,
                'title' => $document->title,
                'created_by' => auth()->id(),
                'tenant_id' => request()->tenant->id,
                'file_size' => $file->getSize(),
                'signers_count' => count($sanitizedSigners)
            ]);

            return redirect()->route('esignature.esignature-documents.show', $document->id)
                ->with('success', 'Document created successfully. You can now add signature fields and send it for signing.');
                
        } catch (\Exception $e) {
            Log::error('Failed to create e-signature document', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => request()->tenant->id,
                'file_name' => $file->getClientOriginalName() ?? 'unknown'
            ]);
            
            return back()->withInput()->with('error', 'Failed to create document. Please try again.');
        }
    }

    /**
     * Display the specified document
     */
    public function show(Request $request, $tenant, $esignature_document)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($esignature_document);
            
        $this->authorize('view', $document);

        $document->load(['signatures', 'fields', 'auditTrail', 'creator']);

        return view('backend.esignature.documents.show', compact('document'));
    }

    /**
     * Show the form for editing the document
     */
    public function edit(Request $request, $tenant, $esignature_document)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($esignature_document);
            
        $this->authorize('update', $document);

        return view('backend.esignature.documents.edit', compact('document'));
    }

    /**
     * Update the specified document
     */
    public function update(Request $request, $tenant, $esignature_document)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($esignature_document);
            
        $this->authorize('update', $document);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string|in:contract,agreement,form,policy,other',
            'custom_message' => 'nullable|string',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_company' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            $document->update([
                'title' => $request->title,
                'description' => $request->description,
                'document_type' => $request->document_type,
                'custom_message' => $request->custom_message,
                'sender_name' => $request->sender_name,
                'sender_email' => $request->sender_email,
                'sender_company' => $request->sender_company,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null,
            ]);

            return redirect()->route('esignature.esignature-documents.show', $document->id)
                ->with('success', 'Document updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update document: ' . $e->getMessage());
        }
    }

    /**
     * Send document for signing
     */
    public function send(Request $request, $tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('send', $document);

        $request->validate([
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string',
            'signers.*.phone' => 'nullable|string',
            'signers.*.company' => 'nullable|string',
        ]);

        try {
            // Update signers
            $document->update([
                'signers' => $request->signers,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Create signature records for each signer with secure tokens
            foreach ($request->signers as $signerData) {
                $signature = ESignatureSignature::create([
                    'document_id' => $document->id,
                    'tenant_id' => request()->tenant->id,
                    'signer_email' => $signerData['email'],
                    'signer_name' => $this->securityService->sanitizeInput($signerData['name']),
                    'signer_phone' => $signerData['phone'] ? $this->securityService->sanitizeInput($signerData['phone']) : null,
                    'signer_company' => $signerData['company'] ? $this->securityService->sanitizeInput($signerData['company']) : null,
                    'signature_token' => ESignatureSignature::generateSecureToken(), // Use secure token generation
                    'status' => 'pending',
                    'expires_at' => $document->expires_at,
                ]);

                // Send legacy email
                try {
                    Mail::to($signerData['email'])->send(new ESignatureDocumentSent($document, $signature));
                } catch (\Exception $e) {
                    \Log::warning('Failed to send legacy e-signature email: ' . $e->getMessage());
                }

                // Send notifications (Email, SMS, Internal) to document creator
                try {
                    $creator = $document->creator;
                    if ($creator) {
                        $creator->notify(new ESignatureDocumentSentNotification($document, $signature));
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to send e-signature document sent notification: ' . $e->getMessage());
                }

                // Create a temporary user for the signer if they exist in the system
                $signerUser = User::where('email', $signerData['email'])->first();
                if ($signerUser) {
                    try {
                        $signerUser->notify(new ESignatureDocumentSentNotification($document, $signature));
                    } catch (\Exception $e) {
                        \Log::warning('Failed to send e-signature notification to signer: ' . $e->getMessage());
                    }
                }
            }

            // Log audit trail
            ESignatureAuditTrail::logDocumentSent($document, auth()->user());

            return redirect()->route('esignature.esignature-documents.show', $document->id)
                ->with('success', 'Document sent for signing successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to send document: ' . $e->getMessage());
        }
    }

    /**
     * Cancel document
     */
    public function cancel(Request $request, $tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('cancel', $document);

        try {
            $document->update(['status' => 'cancelled']);

            // Cancel all pending signatures
            $document->signatures()->where('status', 'pending')->update(['status' => 'cancelled']);

            // Send notifications to document creator
            try {
                $creator = $document->creator;
                if ($creator) {
                    $creator->notify(new ESignatureDocumentCancelledNotification($document));
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to send document cancelled notification: ' . $e->getMessage());
            }

            // Send notifications to all signers
            foreach ($document->signatures as $signature) {
                $signerUser = User::where('email', $signature->signer_email)->first();
                if ($signerUser) {
                    try {
                        $signerUser->notify(new ESignatureDocumentCancelledNotification($document));
                    } catch (\Exception $e) {
                        \Log::warning('Failed to send cancellation notification to signer: ' . $e->getMessage());
                    }
                }
            }

            return redirect()->route('esignature.esignature-documents.index')
                ->with('success', 'Document cancelled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel document: ' . $e->getMessage());
        }
    }

    /**
     * Download the original document
     */
    public function download($tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('download', $document);

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * View the document in browser
     */
    public function view($tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('view', $document);

        // Check if file exists
        if (!Storage::disk('public')->exists($document->file_path)) {
            return back()->with('error', 'File not found.');
        }
        
        $fileContent = Storage::disk('public')->get($document->file_path);
        
        return response($fileContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"'
        ]);
    }

    /**
     * Download the signed document
     */
    public function downloadSigned($tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('download', $document);

        if (!$document->isCompleted()) {
            return back()->with('error', 'Document is not fully signed yet.');
        }

        try {
            $signedPdfPath = $this->eSignatureService->generateSignedDocument($document);
            return response()->download($signedPdfPath, 'signed_' . $document->file_name);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate signed document: ' . $e->getMessage());
        }
    }

    /**
     * Get document statistics
     */
    public function statistics($tenant)
    {
        $tenantId = request()->tenant->id;
        
        $stats = [
            'total_documents' => ESignatureDocument::where('tenant_id', $tenantId)->count(),
            'draft_documents' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
            'sent_documents' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
            'signed_documents' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'signed')->count(),
            'expired_documents' => ESignatureDocument::where('tenant_id', $tenantId)->where('status', 'expired')->count(),
            'pending_signatures' => ESignatureSignature::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'completed_signatures' => ESignatureSignature::where('tenant_id', $tenantId)->where('status', 'signed')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get audit trail for document
     */
    public function auditTrail($tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('view', $document);

        $auditTrail = $document->auditTrail()
            ->with(['signature'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($auditTrail);
    }

    /**
     * Handle signature completion
     */
    public function handleSignatureCompleted(ESignatureSignature $signature)
    {
        $document = $signature->document;
        
        // Send notification to document creator
        try {
            $creator = $document->creator;
            if ($creator) {
                $creator->notify(new ESignatureDocumentSignedNotification($document, $signature));
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send signature completed notification: ' . $e->getMessage());
        }

        // Check if all signatures are completed
        $pendingSignatures = $document->signatures()->where('status', 'pending')->count();
        
        if ($pendingSignatures === 0) {
            // All signatures completed, mark document as completed
            $document->update([
                'status' => 'signed',
                'completed_at' => now()
            ]);

            // Send completion notification to document creator
            try {
                $creator = $document->creator;
                if ($creator) {
                    $creator->notify(new ESignatureDocumentCompletedNotification($document));
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to send document completed notification: ' . $e->getMessage());
            }

            // Send legacy email notification
            try {
                Mail::to($creator->email)->send(new ESignatureDocumentSigned($document));
            } catch (\Exception $e) {
                \Log::warning('Failed to send legacy completion email: ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle document expiration
     */
    public function handleDocumentExpired(ESignatureDocument $document)
    {
        // Update document status
        $document->update(['status' => 'expired']);

        // Cancel all pending signatures
        $document->signatures()->where('status', 'pending')->update(['status' => 'expired']);

        // Send notification to document creator
        try {
            $creator = $document->creator;
            if ($creator) {
                $creator->notify(new ESignatureDocumentExpiredNotification($document));
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send document expired notification: ' . $e->getMessage());
        }

        // Send notifications to all pending signers
        foreach ($document->signatures()->where('status', 'expired')->get() as $signature) {
            $signerUser = User::where('email', $signature->signer_email)->first();
            if ($signerUser) {
                try {
                    $signerUser->notify(new ESignatureDocumentExpiredNotification($document));
                } catch (\Exception $e) {
                    \Log::warning('Failed to send expiration notification to signer: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Send reminder notifications
     */
    public function sendReminders($tenant, $id)
    {
        $document = ESignatureDocument::where('tenant_id', request()->tenant->id)
            ->findOrFail($id);
            
        $this->authorize('view', $document);

        $sentCount = 0;
        
        // Send reminders to all pending signers
        foreach ($document->signatures()->where('status', 'pending')->get() as $signature) {
            // Check if signer exists as user
            $signerUser = User::where('email', $signature->signer_email)->first();
            if ($signerUser) {
                try {
                    $signerUser->notify(new ESignatureReminderNotification($document, $signature));
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::warning('Failed to send reminder notification: ' . $e->getMessage());
                }
            } else {
                // For external signers, send email directly
                try {
                    Mail::to($signature->signer_email)->send(new ESignatureDocumentSent($document, $signature));
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::warning('Failed to send reminder email: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Reminders sent to {$sentCount} signers."
        ]);
    }
}
