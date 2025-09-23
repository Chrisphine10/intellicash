@extends('layouts.app')

@section('title', _lang('Create Voting Position'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ _lang('Create Voting Position') }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('voting.positions.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> {{ _lang('Back to Positions') }}
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('voting.positions.store') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="name">{{ _lang('Position Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                           id="name" name="name" value="{{ old('name') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="description">{{ _lang('Description') }}</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="4">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="max_winners">{{ _lang('Maximum Winners') }} <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('max_winners') is-invalid @enderror" 
                                           id="max_winners" name="max_winners" value="{{ old('max_winners', 1) }}" 
                                           min="1" required>
                                    <small class="form-text text-muted">
                                        {{ _lang('Number of people that can be elected for this position') }}
                                    </small>
                                    @error('max_winners')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>{{ _lang('Help') }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <h6>{{ _lang('Position Types:') }}</h6>
                                        <ul class="small">
                                            <li><strong>{{ _lang('Single Winner:') }}</strong> {{ _lang('Chairperson, Treasurer, Secretary') }}</li>
                                            <li><strong>{{ _lang('Multiple Winners:') }}</strong> {{ _lang('Committee Members, Board Members') }}</li>
                                        </ul>

                                        <h6 class="mt-3">{{ _lang('Examples:') }}</h6>
                                        <ul class="small">
                                            <li>{{ _lang('Chairperson (1 winner)') }}</li>
                                            <li>{{ _lang('Treasurer (1 winner)') }}</li>
                                            <li>{{ _lang('Committee Members (3-5 winners)') }}</li>
                                            <li>{{ _lang('Board of Directors (7 winners)') }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> {{ _lang('Create Position') }}
                                </button>
                                <a href="{{ route('voting.positions.index') }}" class="btn btn-secondary">
                                    {{ _lang('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
