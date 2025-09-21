@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{ _lang('API Documentation') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                                <a class="nav-link active" id="v-pills-overview-tab" data-toggle="pill" href="#v-pills-overview" role="tab">
                                    {{ _lang('Overview') }}
                                </a>
                                <a class="nav-link" id="v-pills-authentication-tab" data-toggle="pill" href="#v-pills-authentication" role="tab">
                                    {{ _lang('Authentication') }}
                                </a>
                                <a class="nav-link" id="v-pills-members-tab" data-toggle="pill" href="#v-pills-members" role="tab">
                                    {{ _lang('Members') }}
                                </a>
                                <a class="nav-link" id="v-pills-payments-tab" data-toggle="pill" href="#v-pills-payments" role="tab">
                                    {{ _lang('Payments') }}
                                </a>
                                <a class="nav-link" id="v-pills-transactions-tab" data-toggle="pill" href="#v-pills-transactions" role="tab">
                                    {{ _lang('Transactions') }}
                                </a>
                                <a class="nav-link" id="v-pills-bank-tab" data-toggle="pill" href="#v-pills-bank" role="tab">
                                    {{ _lang('Bank Accounts') }}
                                </a>
                                <a class="nav-link" id="v-pills-vsla-tab" data-toggle="pill" href="#v-pills-vsla" role="tab">
                                    {{ _lang('VSLA') }}
                                </a>
                                <a class="nav-link" id="v-pills-testing-tab" data-toggle="pill" href="#v-pills-testing" role="tab">
                                    {{ _lang('API Testing') }}
                                </a>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="tab-content" id="v-pills-tabContent">
                                <!-- Overview -->
                                <div class="tab-pane fade show active" id="v-pills-overview" role="tabpanel">
                                    <h5>{{ _lang('API Overview') }}</h5>
                                    <p>{{ _lang('The IntelliCash API provides programmatic access to your cooperative society management system. You can use it to integrate with external systems, build custom applications, or automate processes.') }}</p>
                                    
                                    <h6>{{ _lang('Base URL') }}</h6>
                                    <code>{{ url('/api') }}</code>
                                    
                                    <h6>{{ _lang('API Version') }}</h6>
                                    <p>v1.0.0</p>
                                    
                                    <h6>{{ _lang('Response Format') }}</h6>
                                    <p>{{ _lang('All API responses are returned in JSON format with the following structure:') }}</p>
                                    <pre><code>{
    "success": true,
    "data": { ... },
    "pagination": { ... } // For paginated responses
}</code></pre>
                                    
                                    <h6>{{ _lang('Error Format') }}</h6>
                                    <pre><code>{
    "error": "Error Type",
    "message": "Error description"
}</code></pre>
                                </div>

                                <!-- Authentication -->
                                <div class="tab-pane fade" id="v-pills-authentication" role="tabpanel">
                                    <h5>{{ _lang('Authentication') }}</h5>
                                    <p>{{ _lang('API authentication is done using API keys and secrets. Include these in your request headers:') }}</p>
                                    
                                    <h6>{{ _lang('Headers') }}</h6>
                                    <ul>
                                        <li><code>X-API-Key</code> - Your API key</li>
                                        <li><code>X-API-Secret</code> - Your API secret</li>
                                        <li><code>Content-Type: application/json</code></li>
                                        <li><code>Accept: application/json</code></li>
                                    </ul>
                                    
                                    <h6>{{ _lang('Example Request') }}</h6>
                                    <pre><code>curl -X GET "{{ url('/api/members') }}" \
  -H "X-API-Key: your_api_key" \
  -H "X-API-Secret: your_api_secret" \
  -H "Content-Type: application/json"</code></pre>
                                    
                                    <h6>{{ _lang('Rate Limiting') }}</h6>
                                    <p>{{ _lang('API requests are rate limited per API key. Default limits:') }}</p>
                                    <ul>
                                        <li>{{ _lang('Tenant API Keys: 1000 requests per hour') }}</li>
                                        <li>{{ _lang('Member API Keys: 100 requests per hour') }}</li>
                                    </ul>
                                </div>

                                <!-- Members -->
                                <div class="tab-pane fade" id="v-pills-members" role="tabpanel">
                                    <h5>{{ _lang('Member Endpoints') }}</h5>
                                    
                                    <h6>GET /api/members</h6>
                                    <p>{{ _lang('Get all members with optional filtering') }}</p>
                                    <p><strong>{{ _lang('Query Parameters') }}:</strong></p>
                                    <ul>
                                        <li><code>search</code> - Search by name, member number, email, or mobile</li>
                                        <li><code>status</code> - Filter by status (0 or 1)</li>
                                        <li><code>branch_id</code> - Filter by branch</li>
                                        <li><code>per_page</code> - Number of results per page (default: 20)</li>
                                    </ul>
                                    
                                    <h6>GET /api/members/{id}</h6>
                                    <p>{{ _lang('Get specific member details') }}</p>
                                    
                                    <h6>GET /api/members/{id}/savings-accounts</h6>
                                    <p>{{ _lang('Get member\'s savings accounts with balances') }}</p>
                                    
                                    <h6>GET /api/members/{id}/loans</h6>
                                    <p>{{ _lang('Get member\'s loans') }}</p>
                                    
                                    <h6>GET /api/members/{id}/transactions</h6>
                                    <p>{{ _lang('Get member\'s transaction history') }}</p>
                                    
                                    <h6>POST /api/members</h6>
                                    <p>{{ _lang('Create a new member') }}</p>
                                    <pre><code>{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "mobile": "+254712345678",
    "gender": "male",
    "address": "123 Main St",
    "city": "Nairobi",
    "state": "Nairobi",
    "zip": "00100"
}</code></pre>
                                </div>

                                <!-- Payments -->
                                <div class="tab-pane fade" id="v-pills-payments" role="tabpanel">
                                    <h5>{{ _lang('Payment Endpoints') }}</h5>
                                    
                                    <h6>POST /api/payments/process</h6>
                                    <p>{{ _lang('Process a payment transaction') }}</p>
                                    <pre><code>{
    "member_id": 1,
    "account_id": 1,
    "amount": 1000.00,
    "type": "deposit",
    "method": "cash",
    "description": "Monthly savings",
    "reference": "REF123456",
    "bank_account_id": 1,
    "transaction_date": "2024-01-22"
}</code></pre>
                                    
                                    <h6>POST /api/payments/transfer</h6>
                                    <p>{{ _lang('Transfer funds between accounts') }}</p>
                                    <pre><code>{
    "from_account_id": 1,
    "to_account_id": 2,
    "amount": 500.00,
    "description": "Internal transfer",
    "reference": "TRF123456"
}</code></pre>
                                    
                                    <h6>GET /api/payments/history/{member_id}</h6>
                                    <p>{{ _lang('Get payment history for a member') }}</p>
                                    
                                    <h6>GET /api/payments/balance/{account_id}</h6>
                                    <p>{{ _lang('Get account balance') }}</p>
                                </div>

                                <!-- Transactions -->
                                <div class="tab-pane fade" id="v-pills-transactions" role="tabpanel">
                                    <h5>{{ _lang('Transaction Endpoints') }}</h5>
                                    
                                    <h6>GET /api/transactions</h6>
                                    <p>{{ _lang('Get all transactions with filtering') }}</p>
                                    <p><strong>{{ _lang('Query Parameters') }}:</strong></p>
                                    <ul>
                                        <li><code>member_id</code> - Filter by member</li>
                                        <li><code>account_id</code> - Filter by account</li>
                                        <li><code>type</code> - Filter by transaction type</li>
                                        <li><code>status</code> - Filter by status</li>
                                        <li><code>date_from</code> - Start date (YYYY-MM-DD)</li>
                                        <li><code>date_to</code> - End date (YYYY-MM-DD)</li>
                                        <li><code>amount_min</code> - Minimum amount</li>
                                        <li><code>amount_max</code> - Maximum amount</li>
                                    </ul>
                                    
                                    <h6>GET /api/transactions/{id}</h6>
                                    <p>{{ _lang('Get specific transaction details') }}</p>
                                    
                                    <h6>GET /api/transactions/summary</h6>
                                    <p>{{ _lang('Get transaction summary statistics') }}</p>
                                    
                                    <h6>GET /api/transactions/bank-transactions</h6>
                                    <p>{{ _lang('Get bank transactions') }}</p>
                                    
                                    <h6>GET /api/transactions/vsla-transactions</h6>
                                    <p>{{ _lang('Get VSLA transactions') }}</p>
                                </div>

                                <!-- Bank Accounts -->
                                <div class="tab-pane fade" id="v-pills-bank" role="tabpanel">
                                    <h5>{{ _lang('Bank Account Endpoints') }}</h5>
                                    
                                    <h6>GET /api/bank-accounts</h6>
                                    <p>{{ _lang('Get all bank accounts') }}</p>
                                    
                                    <h6>GET /api/bank-accounts/{id}</h6>
                                    <p>{{ _lang('Get specific bank account details') }}</p>
                                    
                                    <h6>{{ _lang('Response Example') }}</h6>
                                    <pre><code>{
    "success": true,
    "data": {
        "id": 1,
        "account_name": "Main Account",
        "account_number": "1234567890",
        "bank_name": "KCB Bank",
        "current_balance": 50000.00,
        "available_balance": 45000.00,
        "currency": "KES",
        "is_active": true
    }
}</code></pre>
                                </div>

                                <!-- VSLA -->
                                <div class="tab-pane fade" id="v-pills-vsla" role="tabpanel">
                                    <h5>{{ _lang('VSLA Endpoints') }}</h5>
                                    
                                    <h6>GET /api/vsla/meetings</h6>
                                    <p>{{ _lang('Get VSLA meetings') }}</p>
                                    
                                    <h6>GET /api/vsla/settings</h6>
                                    <p>{{ _lang('Get VSLA settings') }}</p>
                                    
                                    <h6>{{ _lang('VSLA Settings Response') }}</h6>
                                    <pre><code>{
    "success": true,
    "data": {
        "share_amount": 100.00,
        "penalty_amount": 50.00,
        "welfare_amount": 25.00,
        "meeting_frequency": "weekly",
        "meeting_time": "10:00",
        "meeting_days": "Monday, Wednesday, Friday",
        "auto_approve_loans": false,
        "max_loan_amount": 10000.00,
        "max_loan_duration_days": 365
    }
}</code></pre>
                                </div>

                                <!-- API Testing -->
                                <div class="tab-pane fade" id="v-pills-testing" role="tabpanel">
                                    <h5>{{ _lang('API Testing Tool') }}</h5>
                                    <p>{{ _lang('Test your API endpoints directly from this interface.') }}</p>
                                    
                                    <form id="api-test-form">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="test_endpoint">{{ _lang('Endpoint') }}</label>
                                                    <input type="text" class="form-control" id="test_endpoint" placeholder="/api/members">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="test_method">{{ _lang('Method') }}</label>
                                                    <select class="form-control" id="test_method">
                                                        <option value="GET">GET</option>
                                                        <option value="POST">POST</option>
                                                        <option value="PUT">PUT</option>
                                                        <option value="DELETE">DELETE</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="test_api_key">{{ _lang('API Key') }}</label>
                                                    <input type="text" class="form-control" id="test_api_key">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="test_api_secret">{{ _lang('API Secret') }}</label>
                                                    <input type="password" class="form-control" id="test_api_secret">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="test_headers">{{ _lang('Additional Headers (JSON)') }}</label>
                                            <textarea class="form-control" id="test_headers" rows="3" placeholder='{"Custom-Header": "value"}'></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="test_body">{{ _lang('Request Body (JSON)') }}</label>
                                            <textarea class="form-control" id="test_body" rows="5" placeholder='{"key": "value"}'></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">{{ _lang('Test API') }}</button>
                                    </form>
                                    
                                    <div id="test-results" class="mt-4" style="display: none;">
                                        <h6>{{ _lang('Response') }}</h6>
                                        <pre id="test-response" class="bg-light p-3"></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#api-test-form').on('submit', function(e) {
        e.preventDefault();
        
        var endpoint = $('#test_endpoint').val();
        var method = $('#test_method').val();
        var apiKey = $('#test_api_key').val();
        var apiSecret = $('#test_api_secret').val();
        var headers = $('#test_headers').val();
        var body = $('#test_body').val();
        
        if (!endpoint || !apiKey || !apiSecret) {
            alert('{{ _lang("Please fill in all required fields") }}');
            return;
        }
        
        var requestData = {
            endpoint: endpoint,
            method: method,
            api_key: apiKey,
            api_secret: apiSecret,
            headers: headers ? JSON.parse(headers) : {},
            body: body
        };
        
        $.ajax({
            url: '{{ route("api.test") }}',
            type: 'POST',
            data: requestData,
            success: function(response) {
                $('#test-results').show();
                $('#test-response').text(JSON.stringify(response, null, 2));
            },
            error: function(xhr) {
                $('#test-results').show();
                $('#test-response').text(JSON.stringify(JSON.parse(xhr.responseText), null, 2));
            }
        });
    });
});
</script>
@endpush
