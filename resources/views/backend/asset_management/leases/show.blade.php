@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-management.dashboard') }}">{{ _lang('Asset Management') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}">{{ _lang('Leases') }}</a></li>
                        <li class="breadcrumb-item active">{{ _lang('Lease Details') }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ _lang('Lease Details') }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">{{ _lang('Lease Information') }}</h4>
                        <div>
                            @if($lease->status === 'active')
                                <form action="{{ route('asset-leases.complete', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to complete this lease?')">
                                        <i class="fas fa-check me-1"></i> {{ _lang('Complete Lease') }}
                                    </button>
                                </form>
                                <form action="{{ route('asset-leases.cancel', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this lease?')">
                                        <i class="fas fa-times me-1"></i> {{ _lang('Cancel Lease') }}
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('asset-leases.index', ['tenant' => app('tenant')->slug]) }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i> {{ _lang('Back') }}
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ _lang('Lease Information') }}</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Lease ID') }}:</strong></td>
                                    <td>{{ $lease->id }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Asset') }}:</strong></td>
                                    <td>
                                        @if($lease->asset)
                                            <a href="{{ route('assets.show', ['tenant' => app('tenant')->slug, 'asset' => $lease->asset->id]) }}">{{ $lease->asset->name }}</a>
                                            <br><small class="text-muted">{{ $lease->asset->asset_code }}</small>
                                        @else
                                            <span class="text-muted">{{ _lang('Asset not found') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Member') }}:</strong></td>
                                    <td>
                                        @if($lease->member)
                                            {{ $lease->member->first_name }} {{ $lease->member->last_name }}
                                        @else
                                            <span class="text-muted">{{ _lang('Member not found') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Start Date') }}:</strong></td>
                                    <td>{{ $lease->start_date }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('End Date') }}:</strong></td>
                                    <td>{{ $lease->end_date }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Status') }}:</strong></td>
                                    <td>
                                        <span class="badge badge-{{ $lease->status === 'active' ? 'success' : ($lease->status === 'completed' ? 'primary' : 'danger') }}">
                                            {{ ucfirst($lease->status) }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ _lang('Financial Information') }}</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>{{ _lang('Daily Rate') }}:</strong></td>
                                    <td>{{ formatAmount($lease->daily_rate) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Days') }}:</strong></td>
                                    <td>{{ $lease->total_days }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Total Amount') }}:</strong></td>
                                    <td>{{ formatAmount($lease->total_amount) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Deposit') }}:</strong></td>
                                    <td>{{ formatAmount($lease->deposit) }}</td>
                                </tr>
                                <tr>
                                    <td><strong>{{ _lang('Balance Due') }}:</strong></td>
                                    <td>{{ formatAmount($lease->total_amount - $lease->deposit) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($lease->notes)
                    <div class="mt-3">
                        <h6>{{ _lang('Notes') }}</h6>
                        <p class="text-muted">{{ $lease->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Payment History') }}</h4>
                </div>
                <div class="card-body">
                    @if($lease->payments && $lease->payments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Date') }}</th>
                                        <th>{{ _lang('Amount') }}</th>
                                        <th>{{ _lang('Method') }}</th>
                                        <th>{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lease->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->payment_date }}</td>
                                        <td>{{ formatAmount($payment->amount) }}</td>
                                        <td>{{ ucfirst($payment->payment_method) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $payment->status === 'completed' ? 'success' : 'warning' }}">
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ _lang('No payments recorded') }}</h5>
                            <p class="text-muted">{{ _lang('Payment records will appear here once payments are made.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Quick Actions') }}</h4>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @if($lease->status === 'active')
                            <form action="{{ route('asset-leases.complete', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Are you sure you want to complete this lease?')">
                                    <i class="fas fa-check me-1"></i> {{ _lang('Complete Lease') }}
                                </button>
                            </form>
                            <form action="{{ route('asset-leases.cancel', ['tenant' => app('tenant')->slug, 'lease' => $lease->id ?? 0]) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to cancel this lease?')">
                                    <i class="fas fa-times me-1"></i> {{ _lang('Cancel Lease') }}
                                </button>
                            </form>
                        @endif
                        @if($lease->id)
                            <a href="{{ route('asset-leases.edit', ['tenant' => app('tenant')->slug, 'asset_lease' => $lease->id]) }}" class="btn btn-primary">
                                <i class="fas fa-edit me-1"></i> {{ _lang('Edit Lease') }}
                            </a>
                        @else
                            <button class="btn btn-primary" disabled>
                                <i class="fas fa-edit me-1"></i> {{ _lang('Edit Lease') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Lease Statistics -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Lease Statistics') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-primary">{{ $lease->total_days }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Total Days') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success">{{ formatAmount($lease->total_amount) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Total Amount') }}</p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h3 class="text-warning">{{ formatAmount($lease->deposit) }}</h3>
                                <p class="text-muted mb-0">{{ _lang('Deposit') }}</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <h3 class="text-danger">{{ formatAmount($lease->total_amount - $lease->deposit) }}</h3>
                            <p class="text-muted mb-0">{{ _lang('Balance Due') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Information -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ _lang('Asset Information') }}</h4>
                </div>
                <div class="card-body">
                    @if($lease->asset)
                        <div class="text-center">
                            <h5>{{ $lease->asset->name }}</h5>
                            <p class="text-muted">{{ $lease->asset->asset_code }}</p>
                            <span class="badge badge-secondary">{{ $lease->asset->category->name }}</span>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h6 class="text-primary">{{ formatAmount($lease->asset->purchase_value) }}</h6>
                                    <p class="text-muted mb-0">{{ _lang('Purchase Value') }}</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <h6 class="text-success">{{ formatAmount($lease->asset->current_value) }}</h6>
                                <p class="text-muted mb-0">{{ _lang('Current Value') }}</p>
                            </div>
                        </div>
                    @else
                        <div class="text-center">
                            <h5 class="text-muted">{{ _lang('Asset not found') }}</h5>
                            <p class="text-muted">{{ _lang('The associated asset has been deleted or is unavailable') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
