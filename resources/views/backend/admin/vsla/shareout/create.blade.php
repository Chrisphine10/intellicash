@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('Create New VSLA Cycle') }}</span>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('vsla.cycles.store') }}" class="validate" autocomplete="off" novalidate>
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Cycle Name') }}</label>
                                <input type="text" class="form-control" name="cycle_name" value="{{ old('cycle_name', 'Annual Cycle ' . date('Y')) }}" required>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Start Date') }}</label>
                                <input type="date" class="form-control" name="start_date" value="{{ old('start_date', date('Y-01-01')) }}" required>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('End Date') }}</label>
                                <input type="date" class="form-control" name="end_date" value="{{ old('end_date', date('Y-12-31')) }}" required>
                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Notes') }}</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="{{ _lang('Optional notes about this cycle') }}">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">{{ _lang('Create Cycle') }}</button>
                            <a href="{{ route('vsla.cycles.index') }}" class="btn btn-light">{{ _lang('Cancel') }}</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <span class="panel-title">{{ _lang('About VSLA Cycles') }}</span>
            </div>
            <div class="card-body">
                <h5>{{ _lang('What is a VSLA Cycle?') }}</h5>
                <p>{{ _lang('A VSLA cycle represents a complete period of savings and lending activities, typically lasting 10-12 months. At the end of each cycle, all accumulated savings and profits are distributed to members in proportion to their contributions.') }}</p>
                
                <h5>{{ _lang('Share-Out Process') }}</h5>
                <ul>
                    <li>{{ _lang('Members receive their original share contributions') }}</li>
                    <li>{{ _lang('Members receive their welfare contributions back') }}</li>
                    <li>{{ _lang('Profits from interest and penalties are distributed proportionally based on share contributions') }}</li>
                    <li>{{ _lang('Outstanding loans are deducted from member payouts') }}</li>
                </ul>
                
                <h5>{{ _lang('Important Notes') }}</h5>
                <ul>
                    <li>{{ _lang('Only one active cycle can exist at a time') }}</li>
                    <li>{{ _lang('Share-out can only be calculated after the cycle end date') }}</li>
                    <li>{{ _lang('All transactions during the cycle period are included in calculations') }}</li>
                    <li>{{ _lang('Administrative costs are deducted from the total available funds') }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
