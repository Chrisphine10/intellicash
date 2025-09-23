<?php

namespace App\Services;

use App\Models\ESignatureDocument;
use App\Models\ESignatureSignature;
use App\Models\ESignatureField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;

class ESignatureService
{
    /**
     * Generate signed PDF document
     */
    public function generateSignedDocument(ESignatureDocument $document): string
    {
        $originalPath = storage_path('app/public/' . $document->file_path);
        $signedPath = storage_path('app/public/esignature/signed/' . time() . '_signed_' . $document->file_name);
        
        // Ensure signed directory exists
        $signedDir = dirname($signedPath);
        if (!File::exists($signedDir)) {
            File::makeDirectory($signedDir, 0755, true);
        }

        // For now, just copy the original document as the signed version
        // In a production environment, you would implement proper PDF signing here
        copy($originalPath, $signedPath);
        
        return $signedPath;
    }

    /**
     * Validate document file
     */
    public function validateDocumentFile($file): array
    {
        $errors = [];
        
        // Check file size (10MB max)
        if ($file->getSize() > 10 * 1024 * 1024) {
            $errors[] = 'File size must be less than 10MB';
        }
        
        // Check file type
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            $errors[] = 'File must be a PDF or Word document';
        }
        
        return $errors;
    }

    /**
     * Get document statistics
     */
    public function getDocumentStatistics(ESignatureDocument $document): array
    {
        return [
            'total_signers' => $document->getSignerCount(),
            'completed_signatures' => $document->getCompletedSignaturesCount(),
            'pending_signatures' => $document->getSignerCount() - $document->getCompletedSignaturesCount(),
            'completion_percentage' => $document->getSignerCount() > 0 
                ? round(($document->getCompletedSignaturesCount() / $document->getSignerCount()) * 100, 2)
                : 0,
            'days_since_sent' => $document->sent_at ? $document->sent_at->diffInDays(now()) : null,
            'days_until_expiry' => $document->expires_at ? now()->diffInDays($document->expires_at, false) : null,
            'is_expired' => $document->isExpired(),
            'is_completed' => $document->isCompleted(),
        ];
    }

    /**
     * Clean up expired documents
     */
    public function cleanupExpiredDocuments(): int
    {
        $expiredDocuments = ESignatureDocument::where('expires_at', '<', now())
            ->where('status', 'sent')
            ->get();
        
        $count = 0;
        foreach ($expiredDocuments as $document) {
            $document->update(['status' => 'expired']);
            $document->signatures()->where('status', 'pending')->update(['status' => 'expired']);
            $count++;
        }
        
        return $count;
    }

    /**
     * Generate audit report
     */
    public function generateAuditReport(ESignatureDocument $document): array
    {
        $auditTrail = $document->auditTrail()
            ->with(['signature'])
            ->orderBy('created_at', 'asc')
            ->get();
        
        $report = [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'created_at' => $document->created_at,
                'sent_at' => $document->sent_at,
                'completed_at' => $document->completed_at,
            ],
            'signatures' => [],
            'audit_trail' => $auditTrail->map(function ($audit) {
                return [
                    'action' => $audit->action,
                    'actor' => $audit->getActorDisplayName(),
                    'timestamp' => $audit->created_at,
                    'description' => $audit->description,
                    'security_info' => $audit->getSecurityInfo(),
                ];
            }),
        ];
        
        foreach ($document->signatures as $signature) {
            $report['signatures'][] = [
                'signer_email' => $signature->signer_email,
                'signer_name' => $signature->signer_name,
                'status' => $signature->status,
                'signed_at' => $signature->signed_at,
                'security_info' => $signature->getBrowserInfo(),
                'signing_duration' => $signature->getSigningDuration(),
            ];
        }
        
        return $report;
    }
}
