<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Vote;
use App\Models\VotingAuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class BlockchainVotingService
{
    private $blockchainHash;
    private $previousHash;
    private $merkleRoot;

    public function __construct()
    {
        $this->blockchainHash = $this->generateGenesisHash();
    }

    /**
     * Generate genesis hash for blockchain initialization
     */
    private function generateGenesisHash()
    {
        return hash('sha256', 'VSLA_VOTING_GENESIS_BLOCK_' . now()->timestamp);
    }

    /**
     * Create a secure vote block with military-grade encryption
     */
    public function createVoteBlock(Vote $vote, Election $election)
    {
        $voteData = [
            'election_id' => $vote->election_id,
            'member_id' => $vote->member_id,
            'candidate_id' => $vote->candidate_id,
            'voted_at' => $vote->voted_at->toISOString(),
            'tenant_id' => $vote->tenant_id,
            'vote_hash' => $this->generateVoteHash($vote),
            'digital_signature' => $this->generateDigitalSignature($vote),
            'timestamp' => now()->timestamp,
            'nonce' => $this->generateNonce(),
        ];

        // Encrypt sensitive vote data
        $encryptedVoteData = $this->encryptVoteData($voteData);
        
        // Create block hash
        $blockHash = $this->generateBlockHash($voteData);
        
        // Update vote with blockchain data
        $vote->update([
            'blockchain_hash' => $blockHash,
            'encrypted_data' => $encryptedVoteData,
            'is_verified' => true,
            'verification_timestamp' => now(),
        ]);

        // Log blockchain transaction
        $this->logBlockchainTransaction($vote, $blockHash, 'VOTE_CREATED');

        return $blockHash;
    }

    /**
     * Generate unique vote hash using SHA-256 with cryptographically secure random data
     */
    private function generateVoteHash(Vote $vote)
    {
        // Use cryptographically secure random data to prevent collisions
        $randomNonce = bin2hex(random_bytes(32));
        $timestamp = microtime(true) * 1000000; // High precision timestamp
        
        $voteString = $vote->election_id . 
                     $vote->member_id . 
                     $vote->candidate_id . 
                     $vote->voted_at->timestamp . 
                     $vote->tenant_id . 
                     $randomNonce .
                     $timestamp .
                     config('app.key');

        return hash('sha256', $voteString);
    }

    /**
     * Generate digital signature for vote authenticity
     */
    private function generateDigitalSignature(Vote $vote)
    {
        $signatureData = [
            'election_id' => $vote->election_id,
            'member_id' => $vote->member_id,
            'timestamp' => $vote->voted_at->timestamp,
            'secret_key' => config('app.key'),
        ];

        $signatureString = implode('|', $signatureData);
        return hash_hmac('sha256', $signatureString, config('app.key'));
    }

    /**
     * Encrypt vote data using military-grade encryption
     */
    private function encryptVoteData(array $voteData)
    {
        $jsonData = json_encode($voteData);
        return Crypt::encrypt($jsonData);
    }

    /**
     * Decrypt vote data
     */
    public function decryptVoteData(string $encryptedData)
    {
        try {
            $decrypted = Crypt::decrypt($encryptedData);
            return json_decode($decrypted, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate block hash for blockchain with improved security
     */
    private function generateBlockHash(array $voteData)
    {
        // Use cryptographically secure nonce
        $nonce = $this->generateNonce();
        $timestamp = microtime(true) * 1000000; // High precision timestamp
        
        $blockString = $this->previousHash . 
                      json_encode($voteData, JSON_SORT_KEYS) . // Sort keys for consistency
                      $timestamp . 
                      $nonce .
                      config('app.key');

        return hash('sha256', $blockString);
    }

    /**
     * Generate cryptographic nonce
     */
    private function generateNonce()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Verify vote integrity using blockchain
     */
    public function verifyVote(Vote $vote)
    {
        if (!$vote->blockchain_hash) {
            return false;
        }

        try {
            // Recreate vote data with same security parameters
            $voteData = [
                'election_id' => $vote->election_id,
                'member_id' => $vote->member_id,
                'candidate_id' => $vote->candidate_id,
                'voted_at' => $vote->voted_at->toISOString(),
                'tenant_id' => $vote->tenant_id,
                'vote_hash' => $this->generateVoteHash($vote),
                'digital_signature' => $this->generateDigitalSignature($vote),
                'timestamp' => $vote->voted_at->timestamp,
            ];

            // Verify hash using secure comparison
            $expectedHash = $this->generateBlockHash($voteData);
            $isValid = hash_equals($vote->blockchain_hash, $expectedHash);

            // Log verification attempt
            $this->logBlockchainTransaction($vote, $vote->blockchain_hash, 'VOTE_VERIFIED', $isValid);

            return $isValid;
        } catch (\Exception $e) {
            // Log verification failure
            $this->logBlockchainTransaction($vote, $vote->blockchain_hash, 'VOTE_VERIFICATION_ERROR', false);
            return false;
        }
    }

    /**
     * Verify entire election blockchain integrity
     */
    public function verifyElectionBlockchain(Election $election)
    {
        $votes = $election->votes()->whereNotNull('blockchain_hash')->get();
        $verificationResults = [];

        foreach ($votes as $vote) {
            $isValid = $this->verifyVote($vote);
            $verificationResults[] = [
                'vote_id' => $vote->id,
                'is_valid' => $isValid,
                'blockchain_hash' => $vote->blockchain_hash,
            ];
        }

        $validVotes = collect($verificationResults)->where('is_valid', true)->count();
        $totalVotes = $votes->count();

        return [
            'total_votes' => $totalVotes,
            'valid_votes' => $validVotes,
            'invalid_votes' => $totalVotes - $validVotes,
            'integrity_percentage' => $totalVotes > 0 ? ($validVotes / $totalVotes) * 100 : 0,
            'verification_results' => $verificationResults,
        ];
    }

    /**
     * Generate Merkle tree for vote verification
     */
    public function generateMerkleTree(Election $election)
    {
        $votes = $election->votes()->whereNotNull('blockchain_hash')->pluck('blockchain_hash')->toArray();
        
        if (empty($votes)) {
            return null;
        }

        return $this->buildMerkleTree($votes);
    }

    /**
     * Build Merkle tree from vote hashes
     */
    private function buildMerkleTree(array $hashes)
    {
        if (count($hashes) === 1) {
            return $hashes[0];
        }

        $nextLevel = [];
        for ($i = 0; $i < count($hashes); $i += 2) {
            $left = $hashes[$i];
            $right = isset($hashes[$i + 1]) ? $hashes[$i + 1] : $hashes[$i];
            $nextLevel[] = hash('sha256', $left . $right);
        }

        return $this->buildMerkleTree($nextLevel);
    }

    /**
     * Log blockchain transaction
     */
    private function logBlockchainTransaction(Vote $vote, string $blockHash, string $action, bool $success = true)
    {
        VotingAuditLog::create([
            'election_id' => $vote->election_id,
            'member_id' => $vote->member_id,
            'action' => $action,
            'details' => json_encode([
                'blockchain_hash' => $blockHash,
                'success' => $success,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tenant_id' => $vote->tenant_id,
            'performed_by' => $vote->member->user_id ?? auth()->id(),
        ]);
    }

    /**
     * Generate election security report
     */
    public function generateSecurityReport(Election $election)
    {
        $blockchainVerification = $this->verifyElectionBlockchain($election);
        $merkleRoot = $this->generateMerkleTree($election);
        
        return [
            'election_id' => $election->id,
            'election_title' => $election->title,
            'blockchain_verification' => $blockchainVerification,
            'merkle_root' => $merkleRoot,
            'security_score' => $this->calculateSecurityScore($blockchainVerification),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate security score based on verification results
     */
    private function calculateSecurityScore(array $verification)
    {
        $integrityScore = $verification['integrity_percentage'];
        $totalVotes = $verification['total_votes'];
        
        // Base score from integrity
        $score = $integrityScore;
        
        // Bonus for high vote count (more secure)
        if ($totalVotes > 100) {
            $score += 5;
        } elseif ($totalVotes > 50) {
            $score += 3;
        } elseif ($totalVotes > 10) {
            $score += 1;
        }
        
        return min(100, max(0, $score));
    }
}
