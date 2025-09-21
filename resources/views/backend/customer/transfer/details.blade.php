<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ _lang('Transfer Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ _lang('Transfer ID:') }}</th>
                                <td>#{{ $transfer->id }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Date:') }}</th>
                                <td>{{ $transfer->created_at->format('M d, Y h:i A') }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Transfer Type:') }}</th>
                                <td>
                                    <span class="badge badge-info">{{ $transfer->transfer_type_text }}</span>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Amount:') }}</th>
                                <td class="font-weight-bold text-primary">
                                    {{ decimalPlace($transfer->amount, currency('KES')) }}
                                </td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Status:') }}</th>
                                <td>
                                    @if($transfer->status == 0)
                                        <span class="badge badge-warning">{{ _lang('Pending') }}</span>
                                    @elseif($transfer->status == 1)
                                        <span class="badge badge-info">{{ _lang('Processing') }}</span>
                                    @elseif($transfer->status == 2)
                                        <span class="badge badge-success">{{ _lang('Completed') }}</span>
                                    @else
                                        <span class="badge badge-danger">{{ _lang('Failed') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">{{ _lang('Beneficiary:') }}</th>
                                <td>{{ $transfer->beneficiary_name }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Account Number:') }}</th>
                                <td>{{ $transfer->beneficiary_account }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Description:') }}</th>
                                <td>{{ $transfer->description ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('From Account:') }}</th>
                                <td>{{ $transfer->debitAccount->savings_type->name }} ({{ $transfer->debitAccount->account_number }})</td>
                            </tr>
                            <tr>
                                <th>{{ _lang('Transaction ID:') }}</th>
                                <td>#{{ $transfer->transaction_id }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($transfer->status == 3)
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle"></i> {{ _lang('Transfer Failed') }}</h6>
                                <p class="mb-0">{{ _lang('This transfer could not be completed. Please try again or contact support if the issue persists.') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
