@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-shield-alt me-2"></i>{{ _lang('Banking Security Standards') }}
        </h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.security.dashboard') }}">{{ _lang('Security') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('security.testing') }}">{{ _lang('Testing') }}</a></li>
                <li class="breadcrumb-item active">{{ _lang('Standards') }}</li>
            </ol>
        </nav>
    </div>

    <!-- Standards Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle me-2"></i>{{ _lang('Security Standards Overview') }}
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        {{ _lang('These are the banking and financial security standards that our system adheres to. Each standard includes specific requirements and compliance measures.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Standards Cards -->
    <div class="row">
        @foreach($standards as $standardKey => $standard)
            <div class="col-lg-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas {{ $standard['icon'] ?? 'fa-shield-alt' }} me-2"></i>{{ $standard['name'] }}
                        </h6>
                        <span class="badge badge-{{ ($standard['compliance_level'] ?? 'medium') == 'high' ? 'success' : (($standard['compliance_level'] ?? 'medium') == 'medium' ? 'warning' : 'danger') }}">
                            {{ ucfirst($standard['compliance_level'] ?? 'medium') }}
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="text-sm text-muted">{{ $standard['description'] }}</p>
                        
                        @if(isset($standard['requirements']) && is_array($standard['requirements']))
                            <h6 class="font-weight-bold text-gray-800 mb-2">{{ _lang('Requirements:') }}</h6>
                            <ul class="list-unstyled">
                                @foreach($standard['requirements'] as $requirement)
                                    <li class="mb-1">
                                        <i class="fas fa-check-circle text-success me-2"></i>{{ $requirement }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if(isset($standard['compliance_measures']) && is_array($standard['compliance_measures']))
                            <h6 class="font-weight-bold text-gray-800 mb-2 mt-3">{{ _lang('Compliance Measures:') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless">
                                    <tbody>
                                        @foreach($standard['compliance_measures'] as $measure => $status)
                                            <tr>
                                                <td class="pl-0">
                                                    <i class="fas fa-{{ $status ? 'check text-success' : 'times text-danger' }} me-2"></i>
                                                    {{ $measure }}
                                                </td>
                                                <td class="text-right pr-0">
                                                    <span class="badge badge-{{ $status ? 'success' : 'danger' }}">
                                                        {{ $status ? _lang('Compliant') : _lang('Non-Compliant') }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if(isset($standard['last_updated']))
                            <div class="mt-3 pt-3 border-top">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>{{ _lang('Last Updated:') }} {{ $standard['last_updated'] }}
                                </small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Compliance Summary -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie me-2"></i>{{ _lang('Overall Compliance Status') }}
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        @php
                            $totalStandards = count($standards);
                            $compliantStandards = collect($standards)->filter(function($standard) {
                                return ($standard['compliance_level'] ?? 'medium') === 'high';
                            })->count();
                            $partialStandards = collect($standards)->filter(function($standard) {
                                return ($standard['compliance_level'] ?? 'medium') === 'medium';
                            })->count();
                            $nonCompliantStandards = collect($standards)->filter(function($standard) {
                                return ($standard['compliance_level'] ?? 'medium') === 'low';
                            })->count();
                            $complianceRate = $totalStandards > 0 ? round(($compliantStandards / $totalStandards) * 100, 1) : 0;
                        @endphp
                        
                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                {{ _lang('Fully Compliant') }}
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $compliantStandards }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                {{ _lang('Partial Compliance') }}
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $partialStandards }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                {{ _lang('Non-Compliant') }}
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $nonCompliantStandards }}</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-times-circle fa-2x text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                {{ _lang('Compliance Rate') }}
                                            </div>
                                            <div class="row no-gutters align-items-center">
                                                <div class="col-auto">
                                                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{ $complianceRate }}%</div>
                                                </div>
                                                <div class="col">
                                                    <div class="progress progress-sm mr-2">
                                                        <div class="progress-bar bg-info" role="progressbar"
                                                             style="width: {{ $complianceRate }}%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-chart-line fa-2x text-info"></i>
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

    <!-- Action Buttons -->
    <div class="row mt-4">
        <div class="col-12 text-center">
            <a href="{{ route('security.testing') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>{{ _lang('Back to Testing') }}
            </a>
            <a href="{{ route('security.testing.run') }}" class="btn btn-success btn-lg ml-2" 
               onclick="event.preventDefault(); document.getElementById('run-compliance-tests').submit();">
                <i class="fas fa-play me-2"></i>{{ _lang('Run Compliance Tests') }}
            </a>
            
            <form id="run-compliance-tests" action="{{ route('security.testing.run') }}" method="POST" style="display: none;">
                @csrf
                <input type="hidden" name="test_type" value="compliance">
            </form>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.border-left-danger {
    border-left: 0.25rem solid #e74a3b !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.progress-sm {
    height: 0.5rem;
}
</style>
@endsection
