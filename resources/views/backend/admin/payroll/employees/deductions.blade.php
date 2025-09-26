@extends('layouts.app')

@section('title', _lang('Employee Deductions'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Employee Deductions') }} - {{ $employee->first_name }} {{ $employee->last_name }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Employee') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('payroll.employees.assign-deductions', $employee->id) }}">
                    @csrf
                    <!-- Hidden input to ensure deduction_ids is always present -->
                    <input type="hidden" name="deduction_ids[]" value="">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h5>{{ _lang('Available Deductions') }}</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="50">{{ _lang('Select') }}</th>
                                            <th>{{ _lang('Deduction Name') }}</th>
                                            <th>{{ _lang('Type') }}</th>
                                            <th>{{ _lang('Amount/Rate') }}</th>
                                            <th>{{ _lang('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($deductions as $deduction)
                                        <tr>
                                            <td>
                                                <input type="checkbox" 
                                                       name="deduction_ids[]" 
                                                       value="{{ $deduction->id }}"
                                                       {{ $employeeDeductions->contains('deduction_id', $deduction->id) ? 'checked' : '' }}
                                                       class="form-check-input">
                                            </td>
                                            <td>
                                                <strong>{{ $deduction->name }}</strong><br>
                                                <small class="text-muted">{{ $deduction->description }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $deduction->type)) }}</span>
                                            </td>
                                            <td>
                                                @if($deduction->type == 'percentage')
                                                    {{ $deduction->rate }}%
                                                @else
                                                    {{ decimalPlace($deduction->amount, currency($deduction->currency ?? 'USD')) }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($deduction->is_active)
                                                    <span class="badge bg-success">{{ _lang('Active') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ _lang('Inactive') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                {{ _lang('No deductions available') }}
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <h5>{{ _lang('Current Assignments') }}</h5>
                            @if($employeeDeductions->count() > 0)
                                <div class="list-group">
                                    @foreach($employeeDeductions as $employeeDeduction)
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $employeeDeduction->deduction->name }}</h6>
                                            <small>{{ _lang('Active') }}</small>
                                        </div>
                                        <p class="mb-1">
                                            @if($employeeDeduction->deduction->type == 'percentage')
                                                {{ $employeeDeduction->deduction->rate }}%
                                            @else
                                                {{ decimalPlace($employeeDeduction->deduction->amount, currency($employeeDeduction->deduction->currency ?? 'USD')) }}
                                            @endif
                                        </p>
                                    </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted">
                                    <i class="fas fa-minus-circle fa-2x mb-2"></i>
                                    <p>{{ _lang('No deductions assigned') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="updateDeductionsBtn">
                                <i class="fas fa-save"></i> {{ _lang('Update Deductions') }}
                            </button>
                            <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$(document).ready(function() {
    // Handle form submission
    $('#updateDeductionsBtn').click(function(e) {
        // Remove all hidden empty inputs
        $('input[name="deduction_ids[]"][value=""]').remove();
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ _lang("Updating...") }}');
    });
    
    // Handle checkbox changes
    $('input[name="deduction_ids[]"]').change(function() {
        // Remove hidden empty input when any checkbox is checked
        if ($(this).is(':checked')) {
            $('input[name="deduction_ids[]"][value=""]').remove();
        }
    });
});
</script>
@endsection
