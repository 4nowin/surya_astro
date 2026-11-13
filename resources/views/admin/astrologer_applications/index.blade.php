@extends('admin.layouts.admin')

@section('title', '| Astro Setu Listing Applications')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2>Astro Setu Listing Applications</h2>
        <p class="text-muted mb-0">Review and approve practitioner listings for the consumer Astro Setu mobile app.</p>
    </div>
</div>

<div class="mb-3">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
</div>

<!-- Status Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.astrologer-applications.index', ['status' => 'pending']) }}">
                    Pending <span class="badge bg-warning text-dark ms-1">{{ $counts['pending'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('admin.astrologer-applications.index', ['status' => 'approved']) }}">
                    Approved <span class="badge bg-success ms-1">{{ $counts['approved'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('admin.astrologer-applications.index', ['status' => 'rejected']) }}">
                    Rejected <span class="badge bg-danger ms-1">{{ $counts['rejected'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'all' ? 'active' : '' }}" href="{{ route('admin.astrologer-applications.index', ['status' => 'all']) }}">
                    All <span class="badge bg-secondary ms-1">{{ $counts['all'] }}</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="8%">Photo</th>
                        <th>Practitioner (Admin)</th>
                        <th>English Listing</th>
                        <th>Hindi Listing</th>
                        <th>Pricing</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th width="10%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $app)
                    <tr>
                        <td>{{ $app->id }}</td>
                        <td>
                            @if($app->image)
                                <img src="{{ $app->image }}" alt="" width="48" height="48" class="rounded-circle object-fit-cover">
                            @else
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="fa fa-user"></i>
                                </div>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $app->admin->name ?? 'Unknown Admin' }}</strong>
                            <div class="text-muted small">{{ $app->admin->email ?? 'No email' }}</div>
                        </td>
                        <td>
                            <div><strong>{{ $app->en_name }}</strong></div>
                            <div class="text-muted small">{{ Str::limit($app->en_expertise, 30) }}</div>
                        </td>
                        <td>
                            <div><strong>{{ $app->hi_name }}</strong></div>
                            <div class="text-muted small">{{ Str::limit($app->hi_expertise, 30) }}</div>
                        </td>
                        <td>
                            <div class="small">Chat: ₹{{ $app->price }}/min</div>
                            <div class="small">Call: ₹{{ $app->call_price }}/min</div>
                        </td>
                        <td>
                            @if($app->status === 'pending')
                                <span class="badge bg-warning text-dark">Pending Review</span>
                            @elseif($app->status === 'approved')
                                <span class="badge bg-success">Approved & Live</span>
                            @elseif($app->status === 'rejected')
                                <span class="badge bg-danger">Rejected</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">{{ $app->submitted_at ? $app->submitted_at->format('M d, Y H:i') : $app->created_at->format('M d, Y') }}</div>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.astrologer-applications.show', $app->id) }}" class="btn btn-outline-primary btn-sm">
                                Review
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            No applications found matching the selected filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($applications->hasPages())
    <div class="card-footer">
        {{ $applications->links() }}
    </div>
    @endif
</div>

@endsection
