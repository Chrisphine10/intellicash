<div class="row">
    <div class="col-md-6">
        <h6>{{ _lang('Asset Information') }}</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>{{ _lang('Asset Code') }}:</strong></td>
                <td>{{ $asset->asset_code }}</td>
            </tr>
            <tr>
                <td><strong>{{ _lang('Category') }}:</strong></td>
                <td>
                    <span class="badge badge-secondary">{{ $asset->category->name }}</span>
                    <br><small class="text-muted">{{ ucfirst($asset->category->type) }}</small>
                </td>
            </tr>
            <tr>
                <td><strong>{{ _lang('Status') }}:</strong></td>
                <td>
                    @if($asset->status == 'active')
                        <span class="badge badge-success">{{ _lang('Active') }}</span>
                    @elseif($asset->status == 'inactive')
                        <span class="badge badge-secondary">{{ _lang('Inactive') }}</span>
                    @elseif($asset->status == 'maintenance')
                        <span class="badge badge-warning">{{ _lang('Maintenance') }}</span>
                    @elseif($asset->status == 'disposed')
                        <span class="badge badge-danger">{{ _lang('Disposed') }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td><strong>{{ _lang('Purchase Value') }}:</strong></td>
                <td>{{ formatAmount($asset->purchase_value) }}</td>
            </tr>
            <tr>
                <td><strong>{{ _lang('Current Value') }}:</strong></td>
                <td>{{ formatAmount($asset->current_value) }}</td>
            </tr>
            <tr>
                <td><strong>{{ _lang('Purchase Date') }}:</strong></td>
                <td>{{ $asset->purchase_date }}</td>
            </tr>
            @if($asset->warranty_expiry)
            <tr>
                <td><strong>{{ _lang('Warranty Expiry') }}:</strong></td>
                <td>{{ $asset->warranty_expiry }}</td>
            </tr>
            @endif
            @if($asset->location)
            <tr>
                <td><strong>{{ _lang('Location') }}:</strong></td>
                <td>{{ $asset->location }}</td>
            </tr>
            @endif
        </table>
    </div>
    <div class="col-md-6">
        <h6>{{ _lang('Lease Information') }}</h6>
        <table class="table table-borderless">
            <tr>
                <td><strong>{{ _lang('Leasable') }}:</strong></td>
                <td>
                    @if($asset->is_leasable)
                        <span class="badge badge-success">{{ _lang('Yes') }}</span>
                    @else
                        <span class="badge badge-secondary">{{ _lang('No') }}</span>
                    @endif
                </td>
            </tr>
            @if($asset->is_leasable)
            <tr>
                <td><strong>{{ _lang('Lease Rate') }}:</strong></td>
                <td>{{ formatAmount($asset->lease_rate) }} / {{ ucfirst($asset->lease_rate_type) }}</td>
            </tr>
            @endif
            <tr>
                <td><strong>{{ _lang('Total Revenue') }}:</strong></td>
                <td>{{ formatAmount($asset->total_lease_revenue ?? 0) }}</td>
            </tr>
            <tr>
                <td><strong>{{ _lang('Current Lease') }}:</strong></td>
                <td>
                    @if($asset->activeLeases->count() > 0)
                        <span class="badge badge-info">{{ _lang('Leased') }}</span>
                        <br><small>{{ _lang('To:') }} {{ $asset->activeLeases->first()->member->first_name }} {{ $asset->activeLeases->first()->member->last_name }}</small>
                    @else
                        <span class="badge badge-success">{{ _lang('Available') }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>

@if($asset->description)
<div class="row mt-3">
    <div class="col-12">
        <h6>{{ _lang('Description') }}</h6>
        <p>{{ $asset->description }}</p>
    </div>
</div>
@endif

@if($asset->notes)
<div class="row mt-3">
    <div class="col-12">
        <h6>{{ _lang('Notes') }}</h6>
        <p>{{ $asset->notes }}</p>
    </div>
</div>
@endif

@if($asset->leases->count() > 0)
<div class="row mt-3">
    <div class="col-12">
        <h6>{{ _lang('Lease History') }}</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ _lang('Lease Number') }}</th>
                        <th>{{ _lang('Member') }}</th>
                        <th>{{ _lang('Start Date') }}</th>
                        <th>{{ _lang('End Date') }}</th>
                        <th>{{ _lang('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($asset->leases as $lease)
                    <tr>
                        <td>{{ $lease->lease_number }}</td>
                        <td>{{ $lease->member->first_name }} {{ $lease->member->last_name }}</td>
                        <td>{{ $lease->start_date }}</td>
                        <td>{{ $lease->end_date ?? _lang('Ongoing') }}</td>
                        <td>
                            @if($lease->status == 'active')
                                <span class="badge badge-success">{{ _lang('Active') }}</span>
                            @elseif($lease->status == 'completed')
                                <span class="badge badge-info">{{ _lang('Completed') }}</span>
                            @elseif($lease->status == 'cancelled')
                                <span class="badge badge-danger">{{ _lang('Cancelled') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
