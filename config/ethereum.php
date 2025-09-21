<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ethereum Integration Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Ethereum blockchain integration
    |
    */

    'enabled' => env('ETHEREUM_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Ethereum RPC Configuration
    |--------------------------------------------------------------------------
    |
    | RPC endpoint for Ethereum network communication
    |
    */
    'rpc_url' => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/your-project-id'),

    /*
    |--------------------------------------------------------------------------
    | Smart Contract Configuration
    |--------------------------------------------------------------------------
    |
    | Smart contract address for storing transaction hashes
    |
    */
    'contract_address' => env('ETHEREUM_CONTRACT_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Account Configuration
    |--------------------------------------------------------------------------
    |
    | Ethereum account for signing transactions
    |
    */
    'account_address' => env('ETHEREUM_ACCOUNT_ADDRESS'),
    'private_key' => env('ETHEREUM_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gas Configuration
    |--------------------------------------------------------------------------
    |
    | Gas settings for Ethereum transactions
    |
    */
    'gas_limit' => env('ETHEREUM_GAS_LIMIT', 300000),
    'gas_price' => env('ETHEREUM_GAS_PRICE', '20000000000'), // 20 Gwei

    /*
    |--------------------------------------------------------------------------
    | Network Configuration
    |--------------------------------------------------------------------------
    |
    | Ethereum network settings
    |
    */
    'network' => env('ETHEREUM_NETWORK', 'mainnet'), // mainnet, goerli, sepolia
    'chain_id' => env('ETHEREUM_CHAIN_ID', 1), // 1 for mainnet, 5 for goerli, 11155111 for sepolia

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    |
    | Settings for transaction processing
    |
    */
    'confirmation_blocks' => env('ETHEREUM_CONFIRMATION_BLOCKS', 3),
    'timeout' => env('ETHEREUM_TIMEOUT', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security-related settings
    |
    */
    'encrypt_private_key' => env('ETHEREUM_ENCRYPT_PRIVATE_KEY', true),
    'require_approval' => env('ETHEREUM_REQUIRE_APPROVAL', true),

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Logging configuration for Ethereum operations
    |
    */
    'log_transactions' => env('ETHEREUM_LOG_TRANSACTIONS', true),
    'log_level' => env('ETHEREUM_LOG_LEVEL', 'info'), // debug, info, warning, error
];
