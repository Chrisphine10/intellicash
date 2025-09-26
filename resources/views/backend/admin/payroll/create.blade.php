@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="card-title">{{ _lang('Create Payroll Period') }}</h4>
                    <a href="{{ route('payroll.periods.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Payroll') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('payroll.periods.store') }}">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="period_name">{{ _lang('Period Name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="period_name" name="period_name" 
                                       value="{{ old('period_name') }}" required>
                                @error('period_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="period_type">{{ _lang('Period Type') }} <span class="text-danger">*</span></label>
                                <select class="form-control" id="period_type" name="period_type" required>
                                    <option value="">{{ _lang('Select Period Type') }}</option>
                                    @foreach($periodTypes as $key => $label)
                                        <option value="{{ $key }}" {{ old('period_type') == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('period_type')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="start_date">{{ _lang('Start Date') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="{{ old('start_date') }}" required>
                                @error('start_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="end_date">{{ _lang('End Date') }} <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="{{ old('end_date') }}" required>
                                @error('end_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pay_date">{{ _lang('Pay Date') }}</label>
                                <input type="date" class="form-control" id="pay_date" name="pay_date" 
                                       value="{{ old('pay_date') }}">
                                @error('pay_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">{{ _lang('Notes') }}</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                        @error('notes')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Employee Selection -->
                    <div class="form-group">
                        <label>{{ _lang('Select Employees') }}</label>
                        <div class="row">
                            @foreach($employees as $employee)
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="employee_ids[]" value="{{ $employee->id }}" 
                                           id="employee_{{ $employee->id }}"
                                           {{ in_array($employee->id, old('employee_ids', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="employee_{{ $employee->id }}">
                                        {{ $employee->full_name }}
                                        <br>
                                        <small class="text-muted">{{ $employee->job_title }} - {{ $employee->department }}</small>
                                    </label>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @error('employee_ids')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> {{ _lang('Create Payroll Period') }}
                        </button>
                        <a href="{{ route('payroll.periods.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> {{ _lang('Cancel') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<script>
$(document).ready(function() {
    // Auto-generate period name based on dates
    $('#start_date, #end_date').on('change', function() {
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (startDate && endDate) {
            var start = new Date(startDate);
            var end = new Date(endDate);
            
            if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
                // Same month
                var monthNames = ["January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"];
                var periodName = monthNames[start.getMonth()] + ' ' + start.getFullYear();
                $('#period_name').val(periodName);
            } else {
                // Different months
                var periodName = startDate + ' to ' + endDate;
                $('#period_name').val(periodName);
            }
        }
    });

    // Set default pay date (7 days after end date)
    $('#end_date').on('change', function() {
        var endDate = new Date($(this).val());
        if (endDate) {
            endDate.setDate(endDate.getDate() + 7);
            var payDate = endDate.toISOString().split('T')[0];
            $('#pay_date').val(payDate);
        }
    });

    // Select all employees checkbox
    $('#select_all_employees').on('change', function() {
        $('input[name="employee_ids[]"]').prop('checked', $(this).prop('checked'));
    });
});
</script>
@endsection
