@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ _lang('VSLA Settings') }}</h4>
            </div>
            <div class="card-body">
                <!-- Alert for AJAX responses -->
                <div id="main_alert" class="alert" style="display: none;">
                    <span class="msg"></span>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ _lang('Note:') }}</strong> {{ _lang('Loan interest rates and penalties are managed through the Loan Products section. VSLA settings focus on meeting frequency, contribution amounts, and member roles.') }}
                </div>
                
                <div class="alert alert-warning" id="meeting_days_help" style="display: none;">
                    <i class="fas fa-calendar-alt"></i>
                    <strong>{{ _lang('Meeting Days Selection:') }}</strong> {{ _lang('Select "Custom (specify days)" from the Meeting Frequency dropdown above to choose specific days of the week for VSLA meetings. You can select multiple days for groups that meet more than once per week.') }}
                </div>
                
                <form method="post" action="{{ route('vsla.settings.update') }}" class="settings-submit">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Share Amount') }}</label>
                                <input type="number" class="form-control" name="share_amount" value="{{ $settings->share_amount }}" step="0.01" min="0" required>
                                <small class="form-text text-muted">{{ _lang('Cost per share') }}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Min Shares Per Member') }}</label>
                                <input type="number" class="form-control" name="min_shares_per_member" value="{{ $settings->min_shares_per_member ?? 1 }}" min="1" required>
                                <small class="form-text text-muted">{{ _lang('Minimum shares a member must hold') }}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Max Shares Per Member') }}</label>
                                <input type="number" class="form-control" name="max_shares_per_member" value="{{ $settings->max_shares_per_member ?? 5 }}" min="1" required>
                                <small class="form-text text-muted">{{ _lang('Maximum shares a member can hold') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Max Shares Per Meeting') }}</label>
                                <input type="number" class="form-control" name="max_shares_per_meeting" value="{{ $settings->max_shares_per_meeting ?? 3 }}" min="1" required>
                                <small class="form-text text-muted">{{ _lang('Maximum shares a member can buy in one meeting') }}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Penalty Amount') }}</label>
                                <input type="number" class="form-control" name="penalty_amount" value="{{ $settings->penalty_amount }}" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Welfare Amount') }}</label>
                                <input type="number" class="form-control" name="welfare_amount" value="{{ $settings->welfare_amount }}" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Max Loan Amount') }}</label>
                                <input type="number" class="form-control" name="max_loan_amount" value="{{ $settings->max_loan_amount }}" step="0.01" min="0">
                                <small class="form-text text-muted">{{ _lang('Leave empty for no limit') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Max Loan Duration (Days)') }}</label>
                                <input type="number" class="form-control" name="max_loan_duration_days" value="{{ $settings->max_loan_duration_days }}" min="1">
                                <small class="form-text text-muted">{{ _lang('Maximum loan duration in days') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Frequency') }}</label>
                                <select class="form-control" name="meeting_frequency" id="meeting_frequency" required>
                                    <option value="weekly" {{ $settings->meeting_frequency == 'weekly' ? 'selected' : '' }}>{{ _lang('Weekly (every 7 days)') }}</option>
                                    <option value="monthly" {{ $settings->meeting_frequency == 'monthly' ? 'selected' : '' }}>{{ _lang('Monthly (every 30 days)') }}</option>
                                    <option value="custom" {{ $settings->meeting_frequency == 'custom' ? 'selected' : '' }}>{{ _lang('Custom (specify days)') }}</option>
                                </select>
                                <small class="form-text text-muted">{{ _lang('Select "Custom" to choose specific days of the week for meetings') }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Meeting Time') }}</label>
                                <input type="time" class="form-control" name="meeting_time" value="{{ $settings->getFormattedMeetingTime() }}" required>
                                <small class="form-text text-muted">{{ _lang('Time when meetings are held') }}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group" id="custom_days_group">
                                <label class="control-label">{{ _lang('Meeting Days of Week') }} <span class="text-danger" id="custom_days_required" style="{{ $settings->meeting_frequency == 'custom' ? '' : 'display: none;' }}">*</span></label>
                                <div id="meeting_days_container" class="border rounded p-3">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="monday" id="monday" {{ in_array('monday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="monday">{{ _lang('Monday') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="tuesday" id="tuesday" {{ in_array('tuesday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="tuesday">{{ _lang('Tuesday') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="wednesday" id="wednesday" {{ in_array('wednesday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="wednesday">{{ _lang('Wednesday') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="thursday" id="thursday" {{ in_array('thursday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="thursday">{{ _lang('Thursday') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="friday" id="friday" {{ in_array('friday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="friday">{{ _lang('Friday') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="saturday" id="saturday" {{ in_array('saturday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="saturday">{{ _lang('Saturday') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input meeting-day-checkbox" type="checkbox" name="meeting_days[]" value="sunday" id="sunday" {{ in_array('sunday', $settings->meeting_days ?? []) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="sunday">{{ _lang('Sunday') }}</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <small class="form-text text-muted" id="meeting_days_help_text">
                                    <span id="weekly_help" style="{{ $settings->meeting_frequency == 'weekly' ? '' : 'display: none;' }}">{{ _lang('For weekly meetings, select the day of the week when meetings are held.') }}</span>
                                    <span id="monthly_help" style="{{ $settings->meeting_frequency == 'monthly' ? '' : 'display: none;' }}">{{ _lang('For monthly meetings, select the day of the week when meetings are held.') }}</span>
                                    <span id="custom_help" style="{{ $settings->meeting_frequency == 'custom' ? '' : 'display: none;' }}">{{ _lang('Select the days of the week when VSLA meetings are held. You can select multiple days for groups that meet more than once per week.') }}</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="auto_approve_loans" value="1" {{ $settings->auto_approve_loans ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ _lang('Auto Approve Loans') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h5 class="mb-3">{{ _lang('Default Item Creation Settings') }}</h5>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>{{ _lang('Note:') }}</strong> {{ _lang('These settings control whether default VSLA items are automatically created when the VSLA module is enabled or when accessing VSLA settings. You can disable any of these options if you prefer to create items manually.') }}
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="create_default_loan_product" value="1" {{ $settings->create_default_loan_product ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ _lang('Create Default Loan Product') }}</label>
                                    <small class="form-text text-muted">{{ _lang('Automatically create "VSLA Default Loan Product" when VSLA is enabled') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="create_default_savings_products" value="1" {{ $settings->create_default_savings_products ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ _lang('Create Default Savings Products') }}</label>
                                    <small class="form-text text-muted">{{ _lang('Automatically create VSLA savings products (Projects, Welfare, Shares, Others, Loan Fund)') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="create_default_bank_accounts" value="1" {{ $settings->create_default_bank_accounts ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ _lang('Create Default Bank Accounts') }}</label>
                                    <small class="form-text text-muted">{{ _lang('Automatically create VSLA bank accounts for fund management') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="create_default_expense_categories" value="1" {{ $settings->create_default_expense_categories ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ _lang('Create Default Expense Categories') }}</label>
                                    <small class="form-text text-muted">{{ _lang('Automatically create expense categories for SACCO/Cooperative operations') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="auto_create_member_accounts" value="1" {{ $settings->auto_create_member_accounts ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ _lang('Auto-Create Member Accounts') }}</label>
                                    <small class="form-text text-muted">{{ _lang('Automatically create savings accounts for new members based on auto-create savings products') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">{{ _lang('Update Settings') }}</button>
                        <a href="{{ route('vsla.meetings.index') }}" class="btn btn-success ml-2">
                            <i class="fas fa-cogs"></i> {{ _lang('Manage VSLA') }}
                        </a>
                    </div>
                </form>
                
                <div class="mt-4">
                    <h5>{{ _lang('VSLA Role Management') }}</h5>
                            <p class="text-muted">{{ _lang('Assign VSLA leadership roles to members. Multiple members can hold the same role.') }}</p>
                            
                            @php
                                $roleHolders = $settings->getRoleHolders();
                            @endphp
                            
                            <!-- Role Management Tabs -->
                            <ul class="nav nav-tabs" id="roleTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="chairperson-tab" data-toggle="tab" href="#chairperson" role="tab">
                                        <i class="fas fa-crown mr-1"></i>{{ _lang('Chairpersons') }} 
                                        <span class="badge badge-primary">{{ $roleHolders['chairperson']->count() }}</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="treasurer-tab" data-toggle="tab" href="#treasurer" role="tab">
                                        <i class="fas fa-coins mr-1"></i>{{ _lang('Treasurers') }} 
                                        <span class="badge badge-success">{{ $roleHolders['treasurer']->count() }}</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="secretary-tab" data-toggle="tab" href="#secretary" role="tab">
                                        <i class="fas fa-file-alt mr-1"></i>{{ _lang('Secretaries') }} 
                                        <span class="badge badge-info">{{ $roleHolders['secretary']->count() }}</span>
                                    </a>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="roleTabsContent">
                                <!-- Chairpersons Tab -->
                                <div class="tab-pane fade show active" id="chairperson" role="tabpanel">
                                    <div class="row mt-3">
                                        <div class="col-md-8">
                                            <h6>{{ _lang('Current Chairpersons') }}</h6>
                                            @if($roleHolders['chairperson']->count() > 0)
                                                <div class="list-group">
                                                    @foreach($roleHolders['chairperson'] as $member)
                                                        @php
                                                            $assignment = $member->activeVslaRoleAssignments->where('role', 'chairperson')->first();
                                                        @endphp
                                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    {{ _lang('Member No:') }} {{ $member->member_no ?? 'N/A' }}
                                                                    @if($assignment && $assignment->notes)
                                                                        | {{ _lang('Notes:') }} {{ $assignment->notes }}
                                                                    @endif
                                                                </small>
                                                            </div>
                                                            <form method="POST" action="{{ route('vsla.settings.remove-role') }}" style="display: inline;" onsubmit="return confirm('{{ _lang('Are you sure you want to remove this role?') }}');">
                                                                @csrf
                                                                <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-times"></i> {{ _lang('Remove') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle"></i> {{ _lang('No chairpersons assigned yet.') }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <h6>{{ _lang('Add New Chairperson') }}</h6>
                                            <form method="POST" action="{{ route('vsla.settings.assign-role') }}">
                                                @csrf
                                                <input type="hidden" name="role" value="chairperson">
                                                <div class="form-group">
                                                    <select class="form-control" name="member_id" required>
                                                        <option value="">{{ _lang('Select Member') }}</option>
                                                        @foreach(\App\Models\Member::where('tenant_id', app('tenant')->id)->whereDoesntHave('activeVslaRoleAssignments', function($q) { $q->where('role', 'chairperson')->where('is_active', true); })->get() as $member)
                                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no ?? 'N/A' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <textarea class="form-control" name="notes" rows="2" placeholder="{{ _lang('Optional notes about this role assignment') }}"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-plus"></i> {{ _lang('Assign Role') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Treasurers Tab -->
                                <div class="tab-pane fade" id="treasurer" role="tabpanel">
                                    <div class="row mt-3">
                                        <div class="col-md-8">
                                            <h6>{{ _lang('Current Treasurers') }}</h6>
                                            @if($roleHolders['treasurer']->count() > 0)
                                                <div class="list-group">
                                                    @foreach($roleHolders['treasurer'] as $member)
                                                        @php
                                                            $assignment = $member->activeVslaRoleAssignments->where('role', 'treasurer')->first();
                                                        @endphp
                                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    {{ _lang('Member No:') }} {{ $member->member_no ?? 'N/A' }}
                                                                    @if($assignment && $assignment->notes)
                                                                        | {{ _lang('Notes:') }} {{ $assignment->notes }}
                                                                    @endif
                                                                </small>
                                                            </div>
                                                            <form method="POST" action="{{ route('vsla.settings.remove-role') }}" style="display: inline;" onsubmit="return confirm('{{ _lang('Are you sure you want to remove this role?') }}');">
                                                                @csrf
                                                                <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-times"></i> {{ _lang('Remove') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle"></i> {{ _lang('No treasurers assigned yet.') }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <h6>{{ _lang('Add New Treasurer') }}</h6>
                                            <form method="POST" action="{{ route('vsla.settings.assign-role') }}">
                                                @csrf
                                                <input type="hidden" name="role" value="treasurer">
                                                <div class="form-group">
                                                    <select class="form-control" name="member_id" required>
                                                        <option value="">{{ _lang('Select Member') }}</option>
                                                        @foreach(\App\Models\Member::where('tenant_id', app('tenant')->id)->whereDoesntHave('activeVslaRoleAssignments', function($q) { $q->where('role', 'treasurer')->where('is_active', true); })->get() as $member)
                                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no ?? 'N/A' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <textarea class="form-control" name="notes" rows="2" placeholder="{{ _lang('Optional notes about this role assignment') }}"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-plus"></i> {{ _lang('Assign Role') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Secretaries Tab -->
                                <div class="tab-pane fade" id="secretary" role="tabpanel">
                                    <div class="row mt-3">
                                        <div class="col-md-8">
                                            <h6>{{ _lang('Current Secretaries') }}</h6>
                                            @if($roleHolders['secretary']->count() > 0)
                                                <div class="list-group">
                                                    @foreach($roleHolders['secretary'] as $member)
                                                        @php
                                                            $assignment = $member->activeVslaRoleAssignments->where('role', 'secretary')->first();
                                                        @endphp
                                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    {{ _lang('Member No:') }} {{ $member->member_no ?? 'N/A' }}
                                                                    @if($assignment && $assignment->notes)
                                                                        | {{ _lang('Notes:') }} {{ $assignment->notes }}
                                                                    @endif
                                                                </small>
                                                            </div>
                                                            <form method="POST" action="{{ route('vsla.settings.remove-role') }}" style="display: inline;" onsubmit="return confirm('{{ _lang('Are you sure you want to remove this role?') }}');">
                                                                @csrf
                                                                <input type="hidden" name="assignment_id" value="{{ $assignment->id }}">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-times"></i> {{ _lang('Remove') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle"></i> {{ _lang('No secretaries assigned yet.') }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-4">
                                            <h6>{{ _lang('Add New Secretary') }}</h6>
                                            <form method="POST" action="{{ route('vsla.settings.assign-role') }}">
                                                @csrf
                                                <input type="hidden" name="role" value="secretary">
                                                <div class="form-group">
                                                    <select class="form-control" name="member_id" required>
                                                        <option value="">{{ _lang('Select Member') }}</option>
                                                        @foreach(\App\Models\Member::where('tenant_id', app('tenant')->id)->whereDoesntHave('activeVslaRoleAssignments', function($q) { $q->where('role', 'secretary')->where('is_active', true); })->get() as $member)
                                                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_no ?? 'N/A' }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <textarea class="form-control" name="notes" rows="2" placeholder="{{ _lang('Optional notes about this role assignment') }}"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-info btn-sm">
                                                    <i class="fas fa-plus"></i> {{ _lang('Assign Role') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h5>{{ _lang('VSLA Account Management') }}</h5>
                            <p class="text-muted">{{ _lang('Sync VSLA accounts for all members. This will create individual VSLA savings accounts for each member with separate accounts for projects, welfare, shares, others, and loan fund.') }}</p>
                            
                            <form method="POST" action="{{ route('vsla.settings.sync-accounts') }}" style="display: inline-block;" onsubmit="return confirm('{{ _lang('This will create VSLA accounts for all members. Continue?') }}');">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-sync-alt mr-2"></i>{{ _lang('Sync Member Accounts') }}
                                </button>
                            </form>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-script')
<style>
.required-field {
    border-left: 3px solid #dc3545 !important;
}

#custom_days_group {
    transition: all 0.3s ease;
}

#meeting_days_container {
    transition: all 0.3s ease;
    background-color: #f8f9fa;
}

#meeting_days_container.border-primary {
    border-color: #007bff !important;
    background-color: #e3f2fd;
}

#meeting_days_container.border-secondary {
    border-color: #6c757d !important;
    background-color: #f8f9fa;
}

#meeting_days_container.border-success {
    border-color: #28a745 !important;
    background-color: #d4edda;
}

#meeting_days_container.border-danger {
    border-color: #dc3545 !important;
    background-color: #f8d7da;
}

.meeting-day-checkbox:checked + label {
    font-weight: bold;
    color: #007bff;
}

.form-check:hover {
    background-color: rgba(0, 123, 255, 0.1);
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

#custom_days_required {
    font-weight: bold;
}

.share-limits-error {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.is-invalid {
    border-color: #dc3545 !important;
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 20%, 40%, 60%, 80% { transform: translateX(0); }
    10%, 30%, 50%, 70% { transform: translateX(-2px); }
}
</style>
<script>
$(document).ready(function() {
    // Function to update meeting days field based on frequency
    function updateMeetingDaysField() {
        var frequency = $('#meeting_frequency').val();
        
        // Update help text visibility
        $('#weekly_help, #monthly_help, #custom_help').hide();
        $('#' + frequency + '_help').show();
        
        // Update required indicator
        if (frequency === 'custom') {
            $('#custom_days_required').show();
            // Don't set required on individual checkboxes - handle validation in JavaScript
            $('#meeting_days_container').addClass('border-primary').removeClass('border-secondary');
        } else {
            $('#custom_days_required').hide();
            $('.meeting-day-checkbox').prop('required', false);
            $('#meeting_days_container').removeClass('border-primary').addClass('border-secondary');
        }
        
        // For weekly/monthly, allow only one day selection
        if (frequency === 'weekly' || frequency === 'monthly') {
            $('.meeting-day-checkbox').off('change').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.meeting-day-checkbox').not(this).prop('checked', false);
                }
                validateMeetingDays();
            });
        } else {
            // For custom, allow multiple selections
            $('.meeting-day-checkbox').off('change').on('change', function() {
                validateMeetingDays();
            });
        }
        
        // Show/hide help alert
        if (frequency === 'custom') {
            $('#meeting_days_help').slideDown(300);
        } else {
            $('#meeting_days_help').slideUp(300);
        }
    }
    
    // Function to validate meeting days selection
    function validateMeetingDays() {
        var checkedBoxes = $('.meeting-day-checkbox:checked').length;
        var frequency = $('#meeting_frequency').val();
        
        if (frequency === 'custom') {
            if (checkedBoxes === 0) {
                $('#meeting_days_container').removeClass('border-success').addClass('border-danger');
                return false;
            } else {
                $('#meeting_days_container').removeClass('border-danger').addClass('border-success');
                return true;
            }
        } else {
            // For weekly/monthly, validation is handled by the single selection logic
            $('#meeting_days_container').removeClass('border-danger border-success');
            return true;
        }
    }
    
    // Initialize on page load
    updateMeetingDaysField();
    
    // Run initial validation
    validateMeetingDays();
    
    // Handle change event
    $('#meeting_frequency').change(function() {
        updateMeetingDaysField();
    });
    
    // Form validation - validate before AJAX submission
    $('form.settings-submit').on('submit', function(e) {
        var frequency = $('#meeting_frequency').val();
        
        if (frequency === 'custom') {
            var isValid = validateMeetingDays();
            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                alert('{{ _lang("Please select at least one meeting day for custom frequency.") }}');
                return false;
            }
        }
        // Don't prevent default - let the AJAX handler take over
    });
    
    // Add hover effects for better UX
    $('.meeting-day-checkbox').hover(
        function() {
            $(this).closest('.form-check').addClass('bg-light');
        },
        function() {
            $(this).closest('.form-check').removeClass('bg-light');
        }
    );
    
    // Share limits validation
    function validateShareLimits() {
        var minShares = parseInt($('input[name="min_shares_per_member"]').val()) || 0;
        var maxShares = parseInt($('input[name="max_shares_per_member"]').val()) || 0;
        var maxPerMeeting = parseInt($('input[name="max_shares_per_meeting"]').val()) || 0;
        
        var isValid = true;
        var errorMessages = [];
        
        if (maxShares < minShares) {
            errorMessages.push('{{ _lang("Maximum shares per member must be greater than or equal to minimum shares per member.") }}');
            isValid = false;
        }
        
        if (maxPerMeeting > maxShares) {
            errorMessages.push('{{ _lang("Maximum shares per meeting cannot exceed maximum shares per member.") }}');
            isValid = false;
        }
        
        // Clear previous error styling
        $('input[name="min_shares_per_member"], input[name="max_shares_per_member"], input[name="max_shares_per_meeting"]')
            .removeClass('is-invalid');
        $('.share-limits-error').remove();
        
        if (!isValid) {
            // Add error styling
            $('input[name="min_shares_per_member"], input[name="max_shares_per_member"], input[name="max_shares_per_meeting"]')
                .addClass('is-invalid');
            
            // Show error message
            var errorHtml = '<div class="alert alert-danger share-limits-error mt-2"><ul class="mb-0">';
            errorMessages.forEach(function(message) {
                errorHtml += '<li>' + message + '</li>';
            });
            errorHtml += '</ul></div>';
            
            $('input[name="max_shares_per_meeting"]').closest('.form-group').after(errorHtml);
        }
        
        return isValid;
    }
    
    // Bind validation to share limit fields
    $('input[name="min_shares_per_member"], input[name="max_shares_per_member"], input[name="max_shares_per_meeting"]')
        .on('input change', function() {
            setTimeout(validateShareLimits, 100); // Small delay to ensure value is updated
        });
    
    // Add share limits validation to form submission
    var originalFormHandler = $('form.settings-submit').data('events') && $('form.settings-submit').data('events').submit;
    $('form.settings-submit').off('submit').on('submit', function(e) {
        var frequency = $('#meeting_frequency').val();
        
        // Meeting days validation
        if (frequency === 'custom') {
            var isValid = validateMeetingDays();
            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                alert('{{ _lang("Please select at least one meeting day for custom frequency.") }}');
                return false;
            }
        }
        
        // Share limits validation
        if (!validateShareLimits()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
        
        // Don't prevent default - let the AJAX handler take over
    });
});
</script>
@endsection
