<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ _lang('Withdrawal Request Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ _lang('Request ID:') }}</th>
                                <td>#{{ $withdrawRequest->id }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Date Submitted:') }}</th>
                                <td>{{ $withdrawRequest->created_at }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Withdrawal Method:') }}</th>
                                <td>
                                    <span class="badge badge-info">{{ $withdrawRequest->method->name ?? 'N/A' }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Amount Requested:') }}</th>
                                <td class="font-weight-bold text-primary">
                                    {{ decimalPlace($withdrawRequest->amount, currency($withdrawRequest->account->savings_type->currency->name ?? 'KES')) }}
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Converted Amount:') }}</th>
                                <td>
                                    {{ decimalPlace($withdrawRequest->converted_amount, currency($withdrawRequest->method->currency->name ?? 'KES')) }}
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Status:') }}</th>
                                <td>
                                    @if($withdrawRequest->status == 0)
                                        <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                    @elseif($withdrawRequest->status == 1)
                                        <span class="badge badge-info">{{ _lang('Processing') }}</span>
                                    @elseif($withdrawRequest->status == 2)
                                        <span class="badge badge-success">{{ _lang('Approved') }}</span>
                                    @else
                                        <span class="badge badge-danger">{{ _lang('Rejected') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ _lang('From Account:') }}</th>
                                <td>
                                    {{ $withdrawRequest->account->account_number ?? 'N/A' }}<br>
                                    <small class="text-muted">{{ $withdrawRequest->account->savings_type->name ?? 'N/A' }}</small>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Description:') }}</th>
                                <td>{{ $withdrawRequest->description ?: '-' }}</td>
                            </tr>
                            @if($withdrawRequest->attachment)
                                <tr>
                                    <th>{{ _lang('Attachment:') }}</th>
                                    <td>
                                        <a href="{{ asset('public/uploads/media/' . $withdrawRequest->attachment) }}" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> {{ _lang('Download') }}
                                        </a>
                                    </td>
                                </tr>
                            @endif
                            @if($withdrawRequest->transaction_id)
                                <tr>
                                    <th>{{ _lang('Transaction ID:') }}</th>
                                    <td>
                                        <a href="{{ route('trasnactions.details', $withdrawRequest->transaction_id) }}" 
                                           target="_blank" class="text-primary">
                                            #{{ $withdrawRequest->transaction_id }}
                                        </a>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>

                @if($withdrawRequest->requirements)
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <h6>{{ _lang('Requirements Submitted:') }}</h6>
                            <div class="card">
                                <div class="card-body">
                                    @php
                                        $requirements = is_array($withdrawRequest->requirements) 
                                            ? $withdrawRequest->requirements 
                                            : json_decode($withdrawRequest->requirements, true);
                                    @endphp
                                    @if($requirements && is_array($requirements))
                                        <table class="table table-borderless">
                                            @foreach($requirements as $key => $value)
                                                <tr>
                                                    <th width="30%">{{ ucwords(str_replace('_', ' ', $key)) }}:</th>
                                                    <td>{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @else
                                        <p class="text-muted">{{ _lang('No requirements submitted') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if($withdrawRequest->status == 3)
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle"></i> {{ _lang('Request Rejected') }}</h6>
                                <p class="mb-0">{{ _lang('This withdrawal request has been rejected. Please contact support for more information.') }}</p>
                            </div>
                        </div>
                    </div>
                @elseif($withdrawRequest->status == 0)
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-clock"></i> {{ _lang('Request Pending') }}</h6>
                                <p class="mb-0">{{ _lang('Your withdrawal request is being reviewed. You will be notified once it has been processed.') }}</p>
                            </div>
                        </div>
                    </div>
                @elseif($withdrawRequest->status == 2)
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle"></i> {{ _lang('Request Approved') }}</h6>
                                <p class="mb-0">{{ _lang('Your withdrawal request has been approved and processed successfully.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
