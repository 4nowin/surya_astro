@extends('admin.layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">{{ __('App Users Management') }}</h4>
            <p class="text-muted mb-0">Browse registered Astro Setu mobile users and astrology profiles.</p>
        </div>
    </div>

    @if ($message = Session::get('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ $message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Profiles Count</th>
                            <th>Primary Profile City</th>
                            <th>Registered</th>
                            <th width="10%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $primaryProfile = $user->astrologyProfiles->firstWhere('is_default', true) ?? $user->astrologyProfiles->first();
                            @endphp
                            <tr>
                                <td>{{ ++$i }}</td>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->phone ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $user->astrologyProfiles->count() }}</span>
                                </td>
                                <td>
                                    @if ($primaryProfile)
                                        <span>{{ $primaryProfile->birth_city }}, {{ $primaryProfile->birth_country }}</span>
                                        <span class="badge bg-light text-dark border ms-1">{{ $primaryProfile->birth_time_precision }}</span>
                                    @else
                                        <span class="text-muted fst-italic">None</span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at ? $user->created_at->format('Y-m-d') : '-' }}</td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('users.show', $user->id) }}">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No app users found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($users->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
