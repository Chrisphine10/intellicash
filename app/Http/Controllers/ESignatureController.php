<?php

namespace App\Http\Controllers;

use App\Models\ESignatureDocument;
use App\Models\ESignatureSignature;
use App\Models\ESignatureField;
use App\Models\ESignatureAuditTrail;
use App\Services\ESignatureService;
use App\Mail\ESignatureDocumentSent;
use App\Mail\ESignatureDocumentSigned;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ESignatureController extends Controller
{
    protected $eSignatureService;

    public function __construct(ESignatureService $eSignatureService)
    {
        $this->eSignatureService = $eSignatureService;
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

        return view('backend.esignature.documents.index', compact('documents'));
    }

    /**
     * Show the form for creating a new document
     */
    public function create()
    {
        return view('backend.esignature.documents.create');
    }

    /**
     * Store a newly created document
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string|in:contract,agreement,form,policy,other',
            'document_file' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'custom_message' => 'nullable|string',
            'sender_name' => 'nullable|string|max:255',
            'sender_email' => 'nullable|email|max:255',
            'sender_company' => 'nullable|string|max:255',
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string',
            'signers.*.phone' => 'nullable|string',
            'signers.*.company' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            // Handle file upload
            $file = $request->file('document_file');
            $fileName = time() . '_' . Str::slug($request->title) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('esignature/documents', $fileName, 'public');

            // Create document
            $document = ESignatureDocument::create([
                'tenant_id' => request()->tenant->id,
                'title' => $request->title,
                'description' => $request->description,
                'document_type' => $request->document_type,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'status' => 'draft',
                'custom_message' => $request->custom_message,
                'sender_name' => $request->sender_name ?: auth()->user()->name,
                'sender_email' => $request->sender_email ?: auth()->user()->email,
                'sender_company' => $request->sender_company,
                'signers' => $request->signers,
                'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null,
                'created_by' => auth()->id(),
            ]);

            // Log audit trail
            ESignatureAuditTrail::logDocumentCreated($document, auth()->user());

            return redirect()->route('esignature.esignature-documents.show', $document->id)
                ->with('success', 'Document created successfully. You can now add signature fields and send it for signing.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create document: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified document
     */
    public function show($id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
            ->findOrFail($id);
            
        $this->authorize('view', $document);

        $document->load(['signatures', 'fields', 'auditTrail', 'creator']);

        return view('backend.esignature.documents.show', compact('document'));
    }

    /**
     * Show the form for editing the document
     */
    public function edit($id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
            ->findOrFail($id);
            
        $this->authorize('update', $document);

        return view('backend.esignature.documents.edit', compact('document'));
    }

    /**
     * Update the specified document
     */
    public function update(Request $request, $id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
            ->findOrFail($id);
            
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
    public function send(Request $request, $id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
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

            // Create signature records for each signer
            foreach ($request->signers as $signerData) {
                $signature = ESignatureSignature::create([
                    'document_id' => $document->id,
                    'tenant_id' => request()->tenant->id,
                    'signer_email' => $signerData['email'],
                    'signer_name' => $signerData['name'],
                    'signer_phone' => $signerData['phone'] ?? null,
                    'signer_company' => $signerData['company'] ?? null,
                    'signature_token' => Str::random(64),
                    'status' => 'pending',
                    'expires_at' => $document->expires_at,
                ]);

                // Send email to signer
                Mail::to($signerData['email'])->send(new ESignatureDocumentSent($document, $signature));
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
    public function cancel($id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
            ->findOrFail($id);
            
        $this->authorize('cancel', $document);

        try {
            $document->update(['status' => 'cancelled']);

            // Cancel all pending signatures
            $document->signatures()->where('status', 'pending')->update(['status' => 'cancelled']);

            return redirect()->route('esignature.esignature-documents.index')
                ->with('success', 'Document cancelled successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel document: ' . $e->getMessage());
        }
    }

    /**
     * Download the original document
     */
    public function download($id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
            ->findOrFail($id);
            
        $this->authorize('download', $document);

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Download the signed document
     */
    public function downloadSigned($id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
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
    public function statistics()
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
    public function auditTrail($id)
    {
        $tenant = app('tenant');
        $document = ESignatureDocument::where('tenant_id', $tenant->id)
            ->findOrFail($id);
            
        $this->authorize('view', $document);

        $auditTrail = $document->auditTrail()
            ->with(['signature'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($auditTrail);
    }
}
