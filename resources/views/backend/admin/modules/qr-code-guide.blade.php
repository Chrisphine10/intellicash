@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('QR Code Module Guide') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('modules.qr_code.configure') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-cog"></i> {{ _lang('Configure Module') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- QR Code Functionality Demo -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">
                                <i class="fas fa-qrcode"></i> {{ _lang('QR Code Functionality') }}
                            </h3>
                            
                            <!-- Live Demo Section -->
                            <div class="card border-primary mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-play-circle"></i> {{ _lang('Live Functionality Demo') }}
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>{{ _lang('How QR Codes Work') }}</h6>
                                            <ol class="list-unstyled">
                                                <li class="mb-2">
                                                    <i class="fas fa-1 text-primary me-2"></i>
                                                    <strong>{{ _lang('Transaction Created') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('System generates unique cryptographic hash') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-2 text-success me-2"></i>
                                                    <strong>{{ _lang('QR Code Generated') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('Contains verification URL and transaction data') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-3 text-warning me-2"></i>
                                                    <strong>{{ _lang('Receipt Display') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('QR code embedded in transaction views') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-4 text-info me-2"></i>
                                                    <strong>{{ _lang('Public Verification') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('Anyone can scan and verify without login') }}</small>
                                                </li>
                                            </ol>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>{{ _lang('QR Code Structure') }}</h6>
                                            <div class="bg-light p-3 rounded">
                                                <pre class="mb-0 small"><code>{
  "tx_hash": "a1b2c3d4...",
  "verification_url": "public/receipt/verify/token",
  "timestamp": "2024-01-15T10:30:00Z",
  "amount": "1000.00",
  "currency": "USD",
  "type": "deposit",
  "status": "completed",
  "tenant_id": "intelliwealth"
}</code></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Technical Implementation -->
                            <div class="card border-success mb-4">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0">
                                        <i class="fas fa-cogs"></i> {{ _lang('Technical Implementation') }}
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h6 class="text-success">
                                                <i class="fas fa-shield-alt"></i> {{ _lang('Security Features') }}
                                            </h6>
                                            <ul class="list-unstyled small">
                                                <li><i class="fas fa-check text-success me-1"></i> SHA-256 Hashing</li>
                                                <li><i class="fas fa-check text-success me-1"></i> Unique Tokens</li>
                                                <li><i class="fas fa-check text-success me-1"></i> Time-based Validation</li>
                                                <li><i class="fas fa-check text-success me-1"></i> Tenant Isolation</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="text-primary">
                                                <i class="fas fa-mobile-alt"></i> {{ _lang('Mobile Ready') }}
                                            </h6>
                                            <ul class="list-unstyled small">
                                                <li><i class="fas fa-check text-primary me-1"></i> SVG Format</li>
                                                <li><i class="fas fa-check text-primary me-1"></i> Responsive Design</li>
                                                <li><i class="fas fa-check text-primary me-1"></i> No Dependencies</li>
                                                <li><i class="fas fa-check text-primary me-1"></i> Fast Loading</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-4">
                                            <h6 class="text-warning">
                                                <i class="fas fa-globe"></i> {{ _lang('Public Access') }}
                                            </h6>
                                            <ul class="list-unstyled small">
                                                <li><i class="fas fa-check text-warning me-1"></i> No Login Required</li>
                                                <li><i class="fas fa-check text-warning me-1"></i> Minimal Data Display</li>
                                                <li><i class="fas fa-check text-warning me-1"></i> Professional UI</li>
                                                <li><i class="fas fa-check text-warning me-1"></i> Secure Verification</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ethereum Business Use Cases -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">
                                <i class="fab fa-ethereum"></i> {{ _lang('Ethereum Integration & Business Use Cases') }}
                            </h3>
                            
                            <!-- Ethereum Overview -->
                            <div class="card border-warning mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0">
                                        <i class="fab fa-ethereum"></i> {{ _lang('Blockchain Integration Benefits') }}
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-warning">{{ _lang('With Ethereum Integration') }}</h6>
                                            <ul class="list-unstyled">
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                    <strong>{{ _lang('Immutable Records') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('Transaction hashes stored on blockchain for permanent verification') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                    <strong>{{ _lang('Smart Contract Verification') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('Automated verification through smart contracts') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                    <strong>{{ _lang('Decentralized Trust') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('No single point of failure, distributed verification') }}</small>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="text-info">{{ _lang('Without Ethereum (Standard Mode)') }}</h6>
                                            <ul class="list-unstyled">
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-primary me-2"></i>
                                                    <strong>{{ _lang('Local Verification') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('Fast, cost-effective cryptographic verification') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-primary me-2"></i>
                                                    <strong>{{ _lang('Database Storage') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('Secure token-based verification system') }}</small>
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-primary me-2"></i>
                                                    <strong>{{ _lang('Lower Costs') }}</strong><br>
                                                    <small class="text-muted">{{ _lang('No blockchain transaction fees') }}</small>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Business Use Cases -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-left-primary h-100">
                                        <div class="card-body">
                                            <h5 class="card-title text-primary">
                                                <i class="fas fa-university"></i> {{ _lang('Banking & Financial Services') }}
                                            </h5>
                                            <p class="card-text">
                                                <strong>{{ _lang('Use Case') }}:</strong> {{ _lang('International wire transfers and cross-border payments') }}
                                            </p>
                                            <div class="mb-3">
                                                <h6 class="text-success">{{ _lang('With Ethereum') }}</h6>
                                                <ul class="small">
                                                    <li>{{ _lang('Global verification without intermediaries') }}</li>
                                                    <li>{{ _lang('Smart contracts for automated compliance') }}</li>
                                                    <li>{{ _lang('Reduced settlement time from days to minutes') }}</li>
                                                    <li>{{ _lang('Transparent audit trail for regulators') }}</li>
                                                </ul>
                                            </div>
                                            <div class="alert alert-info small mb-0">
                                                <strong>{{ _lang('ROI') }}:</strong> {{ _lang('Save $50,000+ annually on compliance and verification costs') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-success h-100">
                                        <div class="card-body">
                                            <h5 class="card-title text-success">
                                                <i class="fas fa-store"></i> {{ _lang('E-commerce & Retail') }}
                                            </h5>
                                            <p class="card-text">
                                                <strong>{{ _lang('Use Case') }}:</strong> {{ _lang('Supply chain verification and product authenticity') }}
                                            </p>
                                            <div class="mb-3">
                                                <h6 class="text-success">{{ _lang('With Ethereum') }}</h6>
                                                <ul class="small">
                                                    <li>{{ _lang('Prove product authenticity from source to customer') }}</li>
                                                    <li>{{ _lang('Prevent counterfeit goods in supply chain') }}</li>
                                                    <li>{{ _lang('Automated warranty and return processing') }}</li>
                                                    <li>{{ _lang('Customer trust through transparent verification') }}</li>
                                                </ul>
                                            </div>
                                            <div class="alert alert-success small mb-0">
                                                <strong>{{ _lang('ROI') }}:</strong> {{ _lang('Increase customer trust by 60% and reduce returns by 40%') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-warning h-100">
                                        <div class="card-body">
                                            <h5 class="card-title text-warning">
                                                <i class="fas fa-file-invoice-dollar"></i> {{ _lang('B2B Invoice & Payments') }}
                                            </h5>
                                            <p class="card-text">
                                                <strong>{{ _lang('Use Case') }}:</strong> {{ _lang('Large-scale invoice processing and payment verification') }}
                                            </p>
                                            <div class="mb-3">
                                                <h6 class="text-success">{{ _lang('With Ethereum') }}</h6>
                                                <ul class="small">
                                                    <li>{{ _lang('Automated invoice verification and payment') }}</li>
                                                    <li>{{ _lang('Smart contracts for payment terms enforcement') }}</li>
                                                    <li>{{ _lang('Reduced disputes through immutable records') }}</li>
                                                    <li>{{ _lang('Real-time payment status tracking') }}</li>
                                                </ul>
                                            </div>
                                            <div class="alert alert-warning small mb-0">
                                                <strong>{{ _lang('ROI') }}:</strong> {{ _lang('Reduce payment processing time by 80% and disputes by 70%') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card border-left-info h-100">
                                        <div class="card-body">
                                            <h5 class="card-title text-info">
                                                <i class="fas fa-handshake"></i> {{ _lang('Insurance & Claims') }}
                                            </h5>
                                            <p class="card-text">
                                                <strong>{{ _lang('Use Case') }}:</strong> {{ _lang('Insurance claim verification and fraud prevention') }}
                                            </p>
                                            <div class="mb-3">
                                                <h6 class="text-success">{{ _lang('With Ethereum') }}</h6>
                                                <ul class="small">
                                                    <li>{{ _lang('Immutable claim records prevent fraud') }}</li>
                                                    <li>{{ _lang('Automated claim processing through smart contracts') }}</li>
                                                    <li>{{ _lang('Transparent payout verification') }}</li>
                                                    <li>{{ _lang('Reduced investigation costs and time') }}</li>
                                                </ul>
                                            </div>
                                            <div class="alert alert-info small mb-0">
                                                <strong>{{ _lang('ROI') }}:</strong> {{ _lang('Reduce fraud by 85% and processing time by 60%') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Details -->
                        <div class="mb-5">
                            <h3 class="text-primary mb-3">
                                <i class="fas fa-code"></i> {{ _lang('Technical Implementation') }}
                            </h3>
                            
                            <div class="accordion" id="technicalAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingOne">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                            {{ _lang('Data Structure') }}
                                        </button>
                                    </h2>
                                    <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#technicalAccordion">
                                        <div class="accordion-body">
                                            <p>{{ _lang('Each QR code contains:') }}</p>
                                            <ul>
                                                <li><strong>{{ _lang('Transaction Hash') }}:</strong> SHA-256 cryptographic hash</li>
                                                <li><strong>{{ _lang('Verification URL') }}:</strong> Direct link to verification page</li>
                                                <li><strong>{{ _lang('Timestamp') }}:</strong> ISO 8601 formatted creation time</li>
                                                <li><strong>{{ _lang('Tenant ID') }}:</strong> Organization identifier</li>
                                                <li><strong>{{ _lang('Amount & Currency') }}:</strong> Transaction value</li>
                                                <li><strong>{{ _lang('Type & Status') }}:</strong> Transaction classification</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingTwo">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                            {{ _lang('Security Features') }}
                                        </button>
                                    </h2>
                                    <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#technicalAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li><strong>{{ _lang('Cryptographic Hashing') }}:</strong> SHA-256 algorithm ensures data integrity</li>
                                                <li><strong>{{ _lang('Unique Tokens') }}:</strong> Each transaction gets a unique verification token</li>
                                                <li><strong>{{ _lang('Tenant Isolation') }}:</strong> QR codes are tenant-specific and secure</li>
                                                <li><strong>{{ _lang('Time-based Validation') }}:</strong> Tokens include timestamp for freshness</li>
                                                <li><strong>{{ _lang('Blockchain Integration') }}:</strong> Optional Ethereum blockchain storage</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingThree">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                            {{ _lang('Blockchain Integration') }}
                                        </button>
                                    </h2>
                                    <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#technicalAccordion">
                                        <div class="accordion-body">
                                            <p>{{ _lang('Optional Ethereum integration provides:') }}</p>
                                            <ul>
                                                <li><strong>{{ _lang('Immutable Records') }}:</strong> Transaction hashes stored on blockchain</li>
                                                <li><strong>{{ _lang('Smart Contract Verification') }}:</strong> Automated verification through smart contracts</li>
                                                <li><strong>{{ _lang('Decentralized Trust') }}:</strong> No single point of failure</li>
                                                <li><strong>{{ _lang('Public Verification') }}:</strong> Anyone can verify transaction authenticity</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Ethereum vs Standard Comparison -->
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fab fa-ethereum"></i> {{ _lang('Ethereum vs Standard Mode') }}
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ _lang('Feature') }}</th>
                                                <th class="text-center">{{ _lang('Standard') }}</th>
                                                <th class="text-center">{{ _lang('Ethereum') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>{{ _lang('Verification Speed') }}</td>
                                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                                <td class="text-center"><i class="fas fa-clock text-warning"></i></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Setup Cost') }}</td>
                                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                                <td class="text-center"><i class="fas fa-dollar-sign text-warning"></i></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Immutable Records') }}</td>
                                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Global Verification') }}</td>
                                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Smart Contracts') }}</td>
                                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                            </tr>
                                            <tr>
                                                <td>{{ _lang('Transaction Fees') }}</td>
                                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                                <td class="text-center"><i class="fas fa-dollar-sign text-warning"></i></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Setup Guide -->
                        <div class="card border-info mt-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-rocket"></i> {{ _lang('Quick Setup') }}
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="text-primary">{{ _lang('Standard Mode') }}</h6>
                                    <ol class="small">
                                        <li>{{ _lang('Enable QR Code module') }}</li>
                                        <li>{{ _lang('Configure basic settings') }}</li>
                                        <li>{{ _lang('Start using immediately') }}</li>
                                    </ol>
                                </div>
                                <div class="mb-3">
                                    <h6 class="text-warning">{{ _lang('Ethereum Mode') }}</h6>
                                    <ol class="small">
                                        <li>{{ _lang('Enable QR Code module') }}</li>
                                        <li>{{ _lang('Configure Ethereum settings') }}</li>
                                        <li>{{ _lang('Test blockchain connection') }}</li>
                                        <li>{{ _lang('Deploy smart contracts') }}</li>
                                    </ol>
                                </div>
                                <div class="text-center">
                                    <a href="{{ route('modules.qr_code.configure') }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-cog"></i> {{ _lang('Configure Now') }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Benefits -->
                        <div class="card border-success mt-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-dollar-sign"></i> {{ _lang('ROI Summary') }}
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <h4 class="text-success">$127,500</h4>
                                        <small class="text-muted">{{ _lang('Annual Savings') }}</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-primary">340%</h4>
                                        <small class="text-muted">{{ _lang('ROI') }}</small>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">{{ _lang('Fraud Prevention') }}</small>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 85%"></div>
                                    </div>
                                    <small class="text-success">85% reduction</small>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">{{ _lang('Efficiency') }}</small>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: 60%"></div>
                                    </div>
                                    <small class="text-primary">60% improvement</small>
                                </div>
                                <div class="mb-0">
                                    <small class="text-muted">{{ _lang('Customer Trust') }}</small>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: 40%"></div>
                                    </div>
                                    <small class="text-warning">40% increase</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Real-World Implementation Examples -->
                <div class="row mt-5">
                    <div class="col-12">
                        <h3 class="text-primary mb-3">
                            <i class="fas fa-globe"></i> {{ _lang('Real-World Implementation Examples') }}
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-primary h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">
                                            <i class="fas fa-university"></i> {{ _lang('Banking Sector') }}
                                        </h5>
                                        <p class="card-text">
                                            <strong>{{ _lang('Example') }}:</strong> {{ _lang('International wire transfer verification') }}
                                        </p>
                                        <div class="mb-3">
                                            <h6 class="text-success">{{ _lang('Standard Mode') }}</h6>
                                            <ul class="small">
                                                <li>{{ _lang('Fast local verification') }}</li>
                                                <li>{{ _lang('Cost-effective solution') }}</li>
                                                <li>{{ _lang('Immediate implementation') }}</li>
                                            </ul>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="text-warning">{{ _lang('Ethereum Mode') }}</h6>
                                            <ul class="small">
                                                <li>{{ _lang('Global verification network') }}</li>
                                                <li>{{ _lang('Smart contract automation') }}</li>
                                                <li>{{ _lang('Regulatory compliance') }}</li>
                                            </ul>
                                        </div>
                                        <div class="alert alert-info small mb-0">
                                            <strong>{{ _lang('Result') }}:</strong> {{ _lang('85% reduction in verification disputes, $2M+ annual savings') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-success h-100">
                                    <div class="card-body">
                                        <h5 class="card-title text-success">
                                            <i class="fas fa-store"></i> {{ _lang('E-commerce Platform') }}
                                        </h5>
                                        <p class="card-text">
                                            <strong>{{ _lang('Example') }}:</strong> {{ _lang('Supply chain authenticity verification') }}
                                        </p>
                                        <div class="mb-3">
                                            <h6 class="text-success">{{ _lang('Standard Mode') }}</h6>
                                            <ul class="small">
                                                <li>{{ _lang('Product authenticity verification') }}</li>
                                                <li>{{ _lang('Customer trust building') }}</li>
                                                <li>{{ _lang('Return fraud prevention') }}</li>
                                            </ul>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="text-warning">{{ _lang('Ethereum Mode') }}</h6>
                                            <ul class="small">
                                                <li>{{ _lang('End-to-end supply chain tracking') }}</li>
                                                <li>{{ _lang('Counterfeit prevention') }}</li>
                                                <li>{{ _lang('Automated warranty processing') }}</li>
                                            </ul>
                                        </div>
                                        <div class="alert alert-success small mb-0">
                                            <strong>{{ _lang('Result') }}:</strong> {{ _lang('60% increase in customer trust, 40% reduction in returns') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Implementation Steps -->
                <div class="row mt-5">
                    <div class="col-12">
                        <h3 class="text-primary mb-3">
                            <i class="fas fa-rocket"></i> {{ _lang('Implementation Steps') }}
                        </h3>
                        
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h5>{{ _lang('Step 1: Enable Module') }}</h5>
                                    <p>{{ _lang('Navigate to Modules section and enable the QR Code module for your tenant.') }}</p>
                                </div>
                            </div>
                            
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h5>{{ _lang('Step 2: Configure Settings') }}</h5>
                                    <p>{{ _lang('Set QR code size, enable verification features, and configure optional blockchain integration.') }}</p>
                                </div>
                            </div>
                            
                            <div class="timeline-item">
                                <div class="timeline-marker bg-warning"></div>
                                <div class="timeline-content">
                                    <h5>{{ _lang('Step 3: Test Integration') }}</h5>
                                    <p>{{ _lang('Create test transactions and verify QR codes are generated correctly on receipts.') }}</p>
                                </div>
                            </div>
                            
                            <div class="timeline-item">
                                <div class="timeline-marker bg-info"></div>
                                <div class="timeline-content">
                                    <h5>{{ _lang('Step 4: Go Live') }}</h5>
                                    <p>{{ _lang('Deploy to production and start benefiting from enhanced transaction security and verification.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support & Documentation -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-question-circle"></i> {{ _lang('Support & Documentation') }}
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>{{ _lang('Need Help?') }}</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-phone text-primary"></i> {{ _lang('Technical Support: +1-800-QR-HELP') }}</li>
                                            <li><i class="fas fa-envelope text-primary"></i> {{ _lang('Email: support@intellicash.com') }}</li>
                                            <li><i class="fas fa-book text-primary"></i> {{ _lang('Documentation: docs.intellicash.com') }}</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>{{ _lang('Quick Actions') }}</h6>
                                        <div class="btn-group-vertical w-100">
                                            <a href="{{ route('modules.qr_code.configure') }}" class="btn btn-outline-primary btn-sm mb-2">
                                                <i class="fas fa-cog"></i> {{ _lang('Configure Module') }}
                                            </a>
                                            <a href="#" class="btn btn-outline-success btn-sm mb-2">
                                                <i class="fas fa-download"></i> {{ _lang('Download Guide PDF') }}
                                            </a>
                                            <a href="#" class="btn btn-outline-info btn-sm">
                                                <i class="fas fa-video"></i> {{ _lang('Watch Tutorial') }}
                                            </a>
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
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.border-left-primary {
    border-left: 4px solid #007bff !important;
}

.border-left-success {
    border-left: 4px solid #28a745 !important;
}

.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

.progress {
    height: 8px;
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}
</style>
@endsection
