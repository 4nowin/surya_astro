@extends('admin.layouts.admin')

@section('title', '| Personalized Daily Insight Rules')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Personalized Daily Insight Rules</h2>
        <p class="text-muted mb-0">Curated Gochara transit interpretation rules for M4B personalized daily insights.</p>
    </div>
    <div>
        <a href="{{ route('daily-insight-rules.create') }}" class="btn btn-primary btn-sm">
            <i class="fa fa-plus"></i> Add New Rule
        </a>
    </div>
</div>

@include('admin.layouts.partials.messages')

<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <form method="GET" action="{{ route('daily-insight-rules.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="category" class="form-label form-label-sm">Category</label>
                <select name="category" id="category" class="form-select form-select-sm">
                    <option value="">-- All Categories --</option>
                    @foreach ($categories as $catKey => $cat)
                        <option value="{{ $catKey }}" {{ request('category') === $catKey ? 'selected' : '' }}>
                            {{ $cat['name_en'] }} ({{ $cat['name_hi'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label for="planet" class="form-label form-label-sm">Planet</label>
                <select name="planet" id="planet" class="form-select form-select-sm">
                    <option value="">-- All Planets --</option>
                    @foreach ($planets as $pKey => $p)
                        <option value="{{ $pKey }}" {{ request('planet') === $pKey ? 'selected' : '' }}>
                            {{ $p['name_en'] }} ({{ $p['name_hi'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-1">
                <label for="house" class="form-label form-label-sm">House</label>
                <select name="house" id="house" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @for ($h = 1; $h <= 12; $h++)
                        <option value="{{ $h }}" {{ request('house') == $h ? 'selected' : '' }}>{{ $h }}</option>
                    @endfor
                </select>
            </div>

            <div class="col-md-2">
                <label for="tone" class="form-label form-label-sm">Tone</label>
                <select name="tone" id="tone" class="form-select form-select-sm">
                    <option value="">-- All Tones --</option>
                    @foreach ($tones as $tKey => $t)
                        <option value="{{ $tKey }}" {{ request('tone') === $tKey ? 'selected' : '' }}>
                            {{ $t['label_en'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label for="search" class="form-label form-label-sm">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Identifier / Headline...">
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('daily-insight-rules.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th>Identifier</th>
                        <th>Category</th>
                        <th>Planet & House</th>
                        <th>Tone</th>
                        <th>Headline (EN / HI)</th>
                        <th>Status</th>
                        <th width="15%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                    <tr>
                        <td>{{ $rule->id }}</td>
                        <td><code>{{ $rule->rule_identifier }}</code></td>
                        <td>
                            <span class="badge bg-secondary">
                                {{ $categories[$rule->category]['name_en'] ?? $rule->category }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ ucfirst($rule->planet) }}</strong> in <strong>H{{ $rule->house }}</strong>
                            @if ($rule->is_retrograde === true)
                                <span class="badge bg-warning text-dark">Retrograde</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeClass = match($rule->tone) {
                                    'favourable' => 'bg-success',
                                    'cautious' => 'bg-danger',
                                    default => 'bg-warning text-dark'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ ucfirst($rule->tone) }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $rule->headline_en }}</div>
                            <small class="text-muted">{{ $rule->headline_hi }}</small>
                        </td>
                        <td>
                            <form action="{{ route('daily-insight-rules.toggle-active', $rule) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-{{ $rule->is_active ? 'success' : 'outline-secondary' }}" title="Click to toggle status">
                                    {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('daily-insight-rules.edit', $rule) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fa fa-pencil"></i> Edit
                            </a>
                            <form action="{{ route('daily-insight-rules.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this interpretation rule?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            No interpretation rules found matching the criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($rules->hasPages())
    <div class="card-footer bg-white">
        {{ $rules->links() }}
    </div>
    @endif
</div>
@endsection
