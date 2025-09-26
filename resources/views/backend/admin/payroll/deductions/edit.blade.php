@extends('layouts.app')

@section('title', _lang('Edit Deduction'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('Edit Deduction') }}</h4>
                <div class="card-tools">
                    <a href="{{ route('payroll.deductions.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> {{ _lang('Back to Deductions') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('payroll.deductions.update', $deduction->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">{{ _lang('Name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="name" value="{{ old('name', $deduction->name) }}" required>
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code">{{ _lang('Code') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="code" id="code" value="{{ old('code', $deduction->code) }}" required>
                                @error('code')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">{{ _lang('Description') }}</label>
                        <textarea class="form-control" name="description" id="description" rows="3">{{ old('description', $deduction->description) }}</textarea>
                        @error('description')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="type">{{ _lang('Type') }} <span class="text-danger">*</span></label>
                                <select class="form-control" name="type" id="type" required>
                                    @foreach(\App\Models\PayrollDeduction::getTypes() as $key => $value)
                                        <option value="{{ $key }}" {{ old('type', $deduction->type) == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tax_category">{{ _lang('Tax Category') }}</label>
                                <select class="form-control" name="tax_category" id="tax_category">
                                    <option value="">{{ _lang('Select Category') }}</option>
                                    @foreach(\App\Models\PayrollDeduction::getTaxCategories() as $key => $value)
                                        <option value="{{ $key }}" {{ old('tax_category', $deduction->tax_category) == $key ? 'selected' : '' }}>
                                            {{ $value }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('tax_category')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group" id="rate-group" style="{{ $deduction->type == 'fixed_amount' ? 'display: none;' : '' }}">
                                <label for="rate">{{ _lang('Rate (%)') }}</label>
                                <input type="number" class="form-control" name="rate" id="rate" value="{{ old('rate', $deduction->rate) }}" min="0" max="100" step="0.01">
                                @error('rate')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group" id="amount-group" style="{{ $deduction->type == 'percentage' ? 'display: none;' : '' }}">
                                <label for="amount">{{ _lang('Amount (KSh)') }}</label>
                                <input type="number" class="form-control" name="amount" id="amount" value="{{ old('amount', $deduction->amount) }}" min="0" step="0.01">
                                @error('amount')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="minimum_amount">{{ _lang('Minimum Amount (KSh)') }}</label>
                                <input type="number" class="form-control" name="minimum_amount" id="minimum_amount" value="{{ old('minimum_amount', $deduction->minimum_amount) }}" min="0" step="0.01">
                                @error('minimum_amount')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="maximum_amount">{{ _lang('Maximum Amount (KSh)') }}</label>
                                <input type="number" class="form-control" name="maximum_amount" id="maximum_amount" value="{{ old('maximum_amount', $deduction->maximum_amount) }}" min="0" step="0.01">
                                @error('maximum_amount')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_mandatory" id="is_mandatory" value="1" {{ old('is_mandatory', $deduction->is_mandatory) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_mandatory">
                                {{ _lang('Mandatory Deduction') }}
                            </label>
                        </div>
                        @error('is_mandatory')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> {{ _lang('Update Deduction') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('type').addEventListener('change', function() {
    const type = this.value;
    const rateGroup = document.getElementById('rate-group');
    const amountGroup = document.getElementById('amount-group');
    
    if (type === 'fixed_amount') {
        rateGroup.style.display = 'none';
        amountGroup.style.display = 'block';
    } else {
        rateGroup.style.display = 'block';
        amountGroup.style.display = 'none';
    }
});
</script>
@endsection
