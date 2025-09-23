@extends('layouts.app')

@section('title', _lang('Voting Positions'))

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="card-title">{{ _lang('Voting Positions') }}</h3>
                        </div>
                        <div class="col-md-6 text-right">
                            <a href="{{ route('voting.positions.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus"></i> {{ _lang('Create Position') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Name') }}</th>
                                    <th>{{ _lang('Description') }}</th>
                                    <th>{{ _lang('Max Winners') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th>{{ _lang('Elections') }}</th>
                                    <th>{{ _lang('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($positions as $position)
                                <tr>
                                    <td>
                                        <strong>{{ $position->name }}</strong>
                                    </td>
                                    <td>
                                        {{ Str::limit($position->description, 100) }}
                                    </td>
                                    <td>
                                        <span class="badge badge-info">{{ $position->max_winners }}</span>
                                    </td>
                                    <td>
                                        @if($position->is_active)
                                            <span class="badge badge-success">{{ _lang('Active') }}</span>
                                        @else
                                            <span class="badge badge-secondary">{{ _lang('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">{{ $position->elections->count() }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('voting.positions.edit', $position->id) }}" 
                                               class="btn btn-warning btn-sm" title="{{ _lang('Edit') }}">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form method="POST" action="{{ route('voting.positions.toggle', $position->id) }}" 
                                                  style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-{{ $position->is_active ? 'secondary' : 'success' }} btn-sm" 
                                                        title="{{ $position->is_active ? _lang('Deactivate') : _lang('Activate') }}">
                                                    <i class="fas fa-{{ $position->is_active ? 'pause' : 'play' }}"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="{{ route('voting.positions.destroy', $position->id) }}" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('{{ _lang('Are you sure you want to delete this position?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        title="{{ _lang('Delete') }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">{{ _lang('No voting positions found') }}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $positions->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
