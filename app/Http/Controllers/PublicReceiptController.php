<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Tenant;
use App\Services\ReceiptQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicReceiptController extends Controller
{
    protected $qrService;

    public function __construct(ReceiptQrService $qrService)
    {
        $this->qrService = $qrService;
    }

    /**
     * Show public receipt verification page
     */
    public function verify(Request $request, $token)
    {
        try {
            // Verify the transaction using the token
            $verificationResult = $this->qrService->verifyTransaction($token);
            
            if (!$verificationResult['valid']) {
                return view('public.receipt.verification', [
                    'error' => $verificationResult['error'] ?? 'Invalid verification token',
                    'transaction' => null,
                    'tenant' => null
                ]);
            }

            $transaction = $verificationResult['transaction'];
            $tenant = $transaction->tenant;

            // Get minimal transaction details for public display
            $receiptData = [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency ?? get_base_currency(),
                'type' => $transaction->type,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at->format('M d, Y H:i:s'),
                'tenant_name' => $tenant->name,
                'verification_token' => $token,
                'verified_at' => now()->format('M d, Y H:i:s')
            ];

            return view('public.receipt.verification', [
                'transaction' => $receiptData,
                'tenant' => $tenant,
                'error' => null
            ]);

        } catch (\Exception $e) {
            Log::error('Public receipt verification error: ' . $e->getMessage());
            
            return view('public.receipt.verification', [
                'error' => 'Unable to verify receipt. Please check the QR code and try again.',
                'transaction' => null,
                'tenant' => null
            ]);
        }
    }

    /**
     * Verify QR code data from mobile app or scanner
     */
    public function verifyQrData(Request $request)
    {
        try {
            $qrData = $request->input('qr_data');
            
            if (!$qrData) {
                return response()->json([
                    'success' => false,
                    'error' => 'QR code data is required'
                ]);
            }

            // Decode QR data
            $decodedData = $this->qrService->decodeQrData($qrData);
            
            if (!$decodedData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid QR code data'
                ]);
            }

            // Verify transaction using the verification token
            $verificationResult = $this->qrService->verifyTransaction($decodedData['verification_token']);
            
            if (!$verificationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'error' => $verificationResult['error'] ?? 'Transaction verification failed'
                ]);
            }

            $transaction = $verificationResult['transaction'];
            $tenant = $transaction->tenant;

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency ?? get_base_currency(),
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at->format('M d, Y H:i:s'),
                    'tenant_name' => $tenant->name,
                    'verified_at' => now()->format('M d, Y H:i:s')
                ],
                'verification_url' => route('public.receipt.verify', ['token' => $decodedData['verification_token']])
            ]);

        } catch (\Exception $e) {
            Log::error('QR data verification error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Unable to verify QR code data'
            ]);
        }
    }
}
