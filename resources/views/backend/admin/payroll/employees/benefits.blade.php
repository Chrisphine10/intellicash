@extends('layouts.app')

@section('title', _lang('Employee Benefits'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ _lang('Employee Benefits') }} - {{ $employee->first_name }} {{ $employee->last_name }}</h3>
                <div class="card-tools">
                    <a href="{{ route('payroll.employees.show', $employee->id) }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Employee') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('payroll.employees.assign-benefits', $employee->id) }}">
                    @csrf
                    <!-- Hidden input to ensure benefit_ids is always present -->
                    <input type="hidden" name="benefit_ids[]" value="">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h5>{{ _lang('Available Benefits') }}</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="50">{{ _lang('Select') }}</th>
                                            <th>{{ _lang('Benefit Name') }}</th>
                                            <th>{{ _lang('Type') }}</th>
                                            <th>{{ _lang('Amount/Rate') }}</th>
                                            <th>{{ _lang('Category') }}</th>
                                            <th>{{ _lang('Status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($benefits as $benefit)
                                        <tr>
                                            <td>
                                                <input type="checkbox" 
                                                       name="benefit_ids[]" 
                                                       value="{{ $benefit->id }}"
                                                       {{ $employeeBenefits->contains('benefit_id', $benefit->id) ? 'checked' : '' }}
                                                       class="form-check-input">
                                            </td>
                                            <td>
                                                <strong>{{ $benefit->name }}</strong><br>
                                                <small class="text-muted">{{ $benefit->description }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $benefit->type)) }}</span>
                                            </td>
                                            <td>
                                                @if($benefit->type == 'percentage')
                                                    {{ $benefit->rate }}%
                                                @else
                                                    {{ decimalPlace($benefit->amount, currency($benefit->currency ?? 'USD')) }}
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $benefit->category)) }}</span>
                                            </td>
                                            <td>
                                                @if($benefit->is_active)
                                                    <span class="badge bg-success">{{ _lang('Active') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ _lang('Inactive') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                {{ _lang('No benefits available') }}
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <h5>{{ _lang('Current Assignments') }}</h5>
                            @if($employeeBenefits->count() > 0)
                                <div class="list-group">
                                    @foreach($employeeBenefits as $employeeBenefit)
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $employeeBenefit->benefit->name }}</h6>
                                            <small>{{ _lang('Active') }}</small>
                                        </div>
                                        <p class="mb-1">
                                            @if($employeeBenefit->benefit->type == 'percentage')
                                                {{ $employeeBenefit->benefit->rate }}%
                                            @else
                                                {{ decimalPlace($employeeBenefit->benefit->amount, currency($employeeBenefit->benefit->currency ?? 'USD')) }}
                                            @endif
                                        </p>
                                        <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $employeeBenefit->benefit->category)) }}</small>
                                    </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center text-muted">
                                    <i class="fas fa-gift fa-2x mb-2"></i>
                                    <p>{{ _lang('No benefits assigned') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="updateBenefitsBtn">
                                <i class="fas fa-save"></i> {{ _lang('Update Benefits') }}
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
    $('#updateBenefitsBtn').click(function(e) {
        // Remove all hidden empty inputs
        $('input[name="benefit_ids[]"][value=""]').remove();
        
        // Show loading state
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> {{ _lang("Updating...") }}');
    });
    
    // Handle checkbox changes
    $('input[name="benefit_ids[]"]').change(function() {
        // Remove hidden empty input when any checkbox is checked
        if ($(this).is(':checked')) {
            $('input[name="benefit_ids[]"][value=""]').remove();
        }
    });
});
</script>
@endsection
