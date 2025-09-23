@extends('layouts.app')

@section('title', _lang('Voting Security Report'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shield-alt"></i> {{ _lang('Voting Security Report') }}</h5>
                </div>
                <div class="card-body">
                    @if(isset($securityReport))
                        <!-- Blockchain Security Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="fas fa-link"></i> {{ _lang('Blockchain Security') }}</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-success">{{ $securityReport['blockchain_verification']['total_votes'] }}</h4>
                                                <p class="mb-0">{{ _lang('Total Votes') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-success">{{ $securityReport['blockchain_verification']['valid_votes'] }}</h4>
                                                <p class="mb-0">{{ _lang('Valid Votes') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-danger">{{ $securityReport['blockchain_verification']['invalid_votes'] }}</h4>
                                                <p class="mb-0">{{ _lang('Invalid Votes') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-info">{{ number_format($securityReport['blockchain_verification']['integrity_percentage'], 1) }}%</h4>
                                                <p class="mb-0">{{ _lang('Integrity Score') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                @if($securityReport['merkle_root'])
                                    <div class="mt-3">
                                        <strong>{{ _lang('Merkle Root:') }}</strong>
                                        <code class="ml-2">{{ $securityReport['merkle_root'] }}</code>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Security Metrics Section -->
                        @if(isset($securityReport['security_metrics']))
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="fas fa-chart-line"></i> {{ _lang('Security Metrics') }}</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-info">{{ $securityReport['security_metrics']['unique_ips'] }}</h4>
                                                <p class="mb-0">{{ _lang('Unique IP Addresses') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-warning">{{ $securityReport['security_metrics']['suspicious_activities'] }}</h4>
                                                <p class="mb-0">{{ _lang('Suspicious Activities') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <h4 class="text-danger">{{ $securityReport['security_metrics']['security_violations'] }}</h4>
                                                <p class="mb-0">{{ _lang('Security Violations') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Security Score Section -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="fas fa-star"></i> {{ _lang('Overall Security Score') }}</h6>
                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar 
                                        @if($securityReport['security_score'] >= 90) bg-success
                                        @elseif($securityReport['security_score'] >= 70) bg-warning
                                        @else bg-danger
                                        @endif" 
                                        role="progressbar" 
                                        style="width: {{ $securityReport['security_score'] }}%"
                                        aria-valuenow="{{ $securityReport['security_score'] }}" 
                                        aria-valuemin="0" 
                                        aria-valuemax="100">
                                        {{ $securityReport['security_score'] }}%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    @if($securityReport['security_score'] >= 90)
                                        {{ _lang('Excellent security level - All systems secure') }}
                                    @elseif($securityReport['security_score'] >= 70)
                                        {{ _lang('Good security level - Minor issues detected') }}
                                    @else
                                        {{ _lang('Security concerns detected - Review required') }}
                                    @endif
                                </small>
                            </div>
                        </div>

                        <!-- Vote Verification Details -->
                        @if(isset($securityReport['blockchain_verification']['verification_results']))
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-primary"><i class="fas fa-check-circle"></i> {{ _lang('Vote Verification Details') }}</h6>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>{{ _lang('Vote ID') }}</th>
                                                <th>{{ _lang('Status') }}</th>
                                                <th>{{ _lang('Blockchain Hash') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($securityReport['blockchain_verification']['verification_results'] as $result)
                                            <tr>
                                                <td>{{ $result['vote_id'] }}</td>
                                                <td>
                                                    @if($result['is_valid'])
                                                        <span class="badge badge-success">{{ _lang('Valid') }}</span>
                                                    @else
                                                        <span class="badge badge-danger">{{ _lang('Invalid') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <code class="small">{{ substr($result['blockchain_hash'], 0, 16) }}...</code>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Report Generation Info -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>{{ _lang('Report Generated:') }}</strong> 
                                    {{ \Carbon\Carbon::parse($securityReport['generated_at'])->format('M d, Y H:i:s') }}
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ _lang('No security data available for this election.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
