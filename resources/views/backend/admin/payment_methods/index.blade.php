@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="header-title">{{ _lang('Payment Methods') }}</span>
                <a class="btn btn-primary btn-sm float-right" href="{{ route('payment_methods.create') }}">
                    <i class="fas fa-plus"></i> {{ _lang('Add New') }}
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="payment_methods_table">
                        <thead>
                            <tr>
                                <th>{{ _lang('Name') }}</th>
                                <th>{{ _lang('Type') }}</th>
                                <th>{{ _lang('Currency') }}</th>
                                <th>{{ _lang('Status') }}</th>
                                <th>{{ _lang('Description') }}</th>
                                <th>{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($paymentMethods as $paymentMethod)
                            <tr>
                                <td>{{ $paymentMethod->name }}</td>
                                <td>
                                    <span class="badge badge-info">{{ ucfirst($paymentMethod->type) }}</span>
                                </td>
                                <td>{{ $paymentMethod->currency->name }}</td>
                                <td>
                                    @if($paymentMethod->is_active)
                                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                                    @else
                                        <span class="badge badge-danger">{{ _lang('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>{{ $paymentMethod->description ?? '-' }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            {{ _lang('Action') }}
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item" href="{{ route('payment_methods.show', $paymentMethod->id) }}">
                                                <i class="fas fa-eye"></i> {{ _lang('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('payment_methods.edit', $paymentMethod->id) }}">
                                                <i class="fas fa-edit"></i> {{ _lang('Edit') }}
                                            </a>
                                            <a class="dropdown-item" href="#" onclick="testConnection({{ $paymentMethod->id }})">
                                                <i class="fas fa-plug"></i> {{ _lang('Test Connection') }}
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" onclick="deletePaymentMethod({{ $paymentMethod->id }})">
                                                <i class="fas fa-trash"></i> {{ _lang('Delete') }}
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testConnection(paymentMethodId) {
    $.ajax({
        url: "{{ route('payment_methods.test', '') }}/" + paymentMethodId,
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
            } else {
                toastr.error(response.message);
            }
        },
        error: function() {
            toastr.error('Connection test failed');
        }
    });
}

function deletePaymentMethod(paymentMethodId) {
    if (confirm('{{ _lang("Are you sure you want to delete this payment method?") }}')) {
        $.ajax({
            url: "{{ route('payment_methods.destroy', '') }}/" + paymentMethodId,
            type: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                location.reload();
            },
            error: function(response) {
                if (response.responseJSON && response.responseJSON.message) {
                    toastr.error(response.responseJSON.message);
                } else {
                    toastr.error('Failed to delete payment method');
                }
            }
        });
    }
}
</script>
@endsection
