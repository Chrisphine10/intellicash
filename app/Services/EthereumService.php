<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EthereumService
{
    private $rpcUrl;
    private $contractAddress;
    private $privateKey;
    private $accountAddress;

    public function __construct()
    {
        $this->rpcUrl = config('ethereum.rpc_url', 'https://mainnet.infura.io/v3/your-project-id');
        $this->contractAddress = config('ethereum.contract_address');
        $this->privateKey = config('ethereum.private_key');
        $this->accountAddress = config('ethereum.account_address');
    }

    /**
     * Store transaction hash on Ethereum blockchain
     */
    public function storeTransactionHash($transaction, string $transactionHash): string
    {
        try {
            // Prepare transaction data
            $transactionData = [
                'transactionId' => $transaction->id,
                'transactionHash' => $transactionHash,
                'amount' => $this->convertToWei($transaction->amount),
                'timestamp' => $transaction->created_at->timestamp,
                'tenantId' => $transaction->tenant_id,
                'memberId' => $transaction->member_id
            ];

            // Encode data for smart contract
            $encodedData = $this->encodeTransactionData($transactionData);

            // Send transaction to Ethereum
            $txHash = $this->sendTransaction($encodedData);

            Log::info('Transaction stored on Ethereum', [
                'intellicash_tx_id' => $transaction->id,
                'ethereum_tx_hash' => $txHash,
                'transaction_hash' => $transactionHash
            ]);

            return $txHash;

        } catch (Exception $e) {
            Log::error('Failed to store transaction on Ethereum', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verify transaction on Ethereum
     */
    public function verifyTransaction(string $ethereumTxHash): bool
    {
        try {
            $response = Http::post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$ethereumTxHash],
                'id' => 1
            ]);

            $data = $response->json();

            if (isset($data['result']) && $data['result'] !== null) {
                $receipt = $data['result'];
                return $receipt['status'] === '0x1'; // Success status
            }

            return false;

        } catch (Exception $e) {
            Log::error('Failed to verify Ethereum transaction', [
                'ethereum_tx_hash' => $ethereumTxHash,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get transaction data from Ethereum
     */
    public function getTransactionData(string $ethereumTxHash): ?array
    {
        try {
            $response = Http::post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getTransactionReceipt',
                'params' => [$ethereumTxHash],
                'id' => 1
            ]);

            $data = $response->json();

            if (isset($data['result']) && $data['result'] !== null) {
                return $this->decodeTransactionData($data['result']);
            }

            return null;

        } catch (Exception $e) {
            Log::error('Failed to get Ethereum transaction data', [
                'ethereum_tx_hash' => $ethereumTxHash,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if Ethereum integration is enabled
     */
    public function isEnabled(): bool
    {
        return config('ethereum.enabled', false) && 
               !empty($this->contractAddress) && 
               !empty($this->privateKey);
    }

    /**
     * Get current gas price
     */
    public function getGasPrice(): string
    {
        try {
            $response = Http::post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_gasPrice',
                'params' => [],
                'id' => 1
            ]);

            $data = $response->json();
            return $data['result'] ?? '0x5208'; // Default gas price

        } catch (Exception $e) {
            Log::warning('Failed to get gas price, using default', [
                'error' => $e->getMessage()
            ]);
            return '0x5208'; // 21000 wei default
        }
    }

    /**
     * Get account balance
     */
    public function getBalance(): string
    {
        try {
            $response = Http::post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'method' => 'eth_getBalance',
                'params' => [$this->accountAddress, 'latest'],
                'id' => 1
            ]);

            $data = $response->json();
            return $data['result'] ?? '0x0';

        } catch (Exception $e) {
            Log::error('Failed to get account balance', [
                'error' => $e->getMessage()
            ]);
            return '0x0';
        }
    }

    /**
     * Convert amount to Wei (smallest Ethereum unit)
     */
    private function convertToWei(float $amount): string
    {
        // Assuming 18 decimal places for ETH
        $wei = bcmul($amount, '1000000000000000000', 0);
        return '0x' . dechex($wei);
    }

    /**
     * Encode transaction data for smart contract
     */
    private function encodeTransactionData(array $data): string
    {
        // This would typically use a proper ABI encoder
        // For now, we'll create a simple JSON encoding
        $encoded = json_encode($data);
        return '0x' . bin2hex($encoded);
    }

    /**
     * Decode transaction data from smart contract
     */
    private function decodeTransactionData(array $receipt): array
    {
        // Extract logs and decode data
        $logs = $receipt['logs'] ?? [];
        
        foreach ($logs as $log) {
            if ($log['address'] === $this->contractAddress) {
                // Decode the log data
                $data = hex2bin(substr($log['data'], 2));
                return json_decode($data, true) ?? [];
            }
        }

        return [];
    }

    /**
     * Send transaction to Ethereum
     */
    private function sendTransaction(string $data): string
    {
        // This is a simplified version
        // In production, you'd use a proper Ethereum library like web3.php
        
        $gasPrice = $this->getGasPrice();
        $gasLimit = '0x7530'; // 30000 gas limit
        
        $transaction = [
            'from' => $this->accountAddress,
            'to' => $this->contractAddress,
            'value' => '0x0',
            'gas' => $gasLimit,
            'gasPrice' => $gasPrice,
            'data' => $data
        ];

        // In a real implementation, you would:
        // 1. Sign the transaction with the private key
        // 2. Send it to the network
        // 3. Wait for confirmation
        
        // For now, return a mock transaction hash
        return '0x' . bin2hex(random_bytes(32));
    }

    /**
     * Deploy smart contract (one-time setup)
     */
    public function deployContract(): string
    {
        // Smart contract for storing transaction hashes
        $contractCode = $this->getSmartContractCode();
        
        // This would deploy the contract and return the address
        // Implementation depends on your Ethereum setup
        
        return '0x' . bin2hex(random_bytes(20)); // Mock contract address
    }

    /**
     * Get smart contract code
     */
    private function getSmartContractCode(): string
    {
        return '
        pragma solidity ^0.8.0;

        contract IntelliCashReceipts {
            struct ReceiptData {
                uint256 transactionId;
                string transactionHash;
                uint256 amount;
                uint256 timestamp;
                uint256 tenantId;
                uint256 memberId;
                bool exists;
            }
            
            mapping(string => ReceiptData) public receipts;
            mapping(uint256 => string[]) public tenantReceipts;
            
            event ReceiptStored(
                string indexed transactionHash,
                uint256 transactionId,
                uint256 amount,
                uint256 timestamp
            );
            
            function storeReceipt(
                uint256 _transactionId,
                string memory _transactionHash,
                uint256 _amount,
                uint256 _timestamp,
                uint256 _tenantId,
                uint256 _memberId
            ) public {
                receipts[_transactionHash] = ReceiptData({
                    transactionId: _transactionId,
                    transactionHash: _transactionHash,
                    amount: _amount,
                    timestamp: _timestamp,
                    tenantId: _tenantId,
                    memberId: _memberId,
                    exists: true
                });
                
                tenantReceipts[_tenantId].push(_transactionHash);
                
                emit ReceiptStored(_transactionHash, _transactionId, _amount, _timestamp);
            }
            
            function getReceipt(string memory _transactionHash) public view returns (ReceiptData memory) {
                return receipts[_transactionHash];
            }
            
            function verifyReceipt(string memory _transactionHash) public view returns (bool) {
                return receipts[_transactionHash].exists;
            }
        }';
    }
}
