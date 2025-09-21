<?php

namespace App\Http\Controllers;

use App\Services\ReceiptQrService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ReceiptVerificationController extends Controller
{
    private $qrService;

    public function __construct(ReceiptQrService $qrService)
    {
        $this->qrService = $qrService;
    }

    /**
     * Verify receipt using QR code token
     */
    public function verify(Request $request, string $token): JsonResponse|View
    {
        try {
            $verificationResult = $this->qrService->verifyTransaction($token);

            if ($request->expectsJson()) {
                return response()->json($verificationResult);
            }

            return view('receipt.verification', [
                'verification' => $verificationResult,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'valid' => false,
                    'error' => 'Verification failed: ' . $e->getMessage()
                ], 500);
            }

            return view('receipt.verification', [
                'verification' => [
                    'valid' => false,
                    'error' => 'Verification failed: ' . $e->getMessage()
                ],
                'token' => $token
            ]);
        }
    }

    /**
     * Verify receipt using QR code data
     */
    public function verifyQrData(Request $request): JsonResponse
    {
        $request->validate([
            'qr_data' => 'required|string'
        ]);

        try {
            $qrData = $this->qrService->decodeQrData($request->qr_data);
            
            if (empty($qrData['verification_token'])) {
                return response()->json([
                    'valid' => false,
                    'error' => 'Invalid QR code data'
                ], 400);
            }

            $verificationResult = $this->qrService->verifyTransaction($qrData['verification_token']);
            
            return response()->json($verificationResult);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get QR code for transaction
     */
    public function getQrCode(Request $request, int $transactionId): JsonResponse
    {
        try {
            $transaction = \App\Models\Transaction::findOrFail($transactionId);
            $tenant = app('tenant');
            
            if (!$tenant || !$tenant->isQrCodeEnabled()) {
                return response()->json(['error' => 'QR Code module not enabled'], 200);
            }

            $qrCodeData = $this->qrService->generateQrCode($transaction);
            
            return response()->json([
                'qr_code' => $qrCodeData,
                'verification_url' => $this->qrService->generateQrData($transaction)['verification_url']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate QR code'], 500);
        }
    }

    /**
     * Check if user can view transaction
     */
    private function canViewTransaction($transaction, $user): bool
    {
        // Admin users can view all transactions
        if ($user->user_type === 'admin' || $user->user_type === 'system_admin') {
            return true;
        }

        // Regular users can only view their own transactions
        if ($user->user_type === 'user') {
            return $transaction->member_id === $user->id;
        }

        return false;
    }
}
