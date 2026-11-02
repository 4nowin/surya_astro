@extends('admin.layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">{{ __('User Details') }}</h4>
            <p class="text-muted mb-0">Inspect user account and registered Vedic astrology profiles.</p>
        </div>
        <div>
            <a class="btn btn-secondary btn-sm" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('users.index') }}">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- User Account Information -->
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 text-primary">
                <i class="fas fa-user-circle me-2"></i>Account Overview
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="text-muted small d-block">Full Name</label>
                    <span class="fw-semibold">{{ $user->name ?? 'N/A' }}</span>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small d-block">Email Address</label>
                    <span class="fw-semibold">{{ $user->email ?? 'N/A' }}</span>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small d-block">Phone Number</label>
                    <span>{{ $user->phone ?? 'N/A' }}</span>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small d-block">Gender</label>
                    <span class="text-capitalize">{{ $user->gender ?? 'N/A' }}</span>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small d-block">Account Role</label>
                    <span class="badge bg-primary text-uppercase">{{ $user->role ?? 'Free' }}</span>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small d-block">Registered At</label>
                    <span>{{ $user->created_at ? $user->created_at->format('Y-m-d H:i') : 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Astrology Profiles Information -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 text-primary">
                <i class="fas fa-star-of-david me-2"></i>Astrology Birth Profiles (Mini-Phase 2)
            </h5>
            <span class="badge bg-secondary">{{ $user->astrologyProfiles ? $user->astrologyProfiles->count() : 0 }} Profile(s)</span>
        </div>
        <div class="card-body p-0">
            @if ($user->astrologyProfiles && $user->astrologyProfiles->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Profile Name / Relation</th>
                                <th>Date of Birth</th>
                                <th>Precision & Time</th>
                                <th>Birth Location</th>
                                <th>Coordinates & Timezone</th>
                                <th>Engine Safeguards</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($user->astrologyProfiles as $profile)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $profile->name }}</div>
                                        <small class="text-muted text-capitalize">{{ $profile->relationship }}</small>
                                        @if ($profile->is_default)
                                            <span class="badge bg-success ms-1">Primary</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $profile->date_of_birth ? $profile->date_of_birth->format('Y-m-d') : 'N/A' }}
                                    </td>
                                    <td>
                                        @if ($profile->birth_time_precision === 'verified')
                                            <span class="badge bg-success">Verified</span>
                                            <div class="small fw-semibold mt-1">{{ $profile->birth_time }}</div>
                                        @elseif ($profile->birth_time_precision === 'approximate')
                                            <span class="badge bg-warning text-dark">Approximate</span>
                                            <div class="small text-muted mt-1">{{ ucwords(str_replace('_', ' ', $profile->approximate_time_window)) }}</div>
                                        @else
                                            <span class="badge bg-secondary">Unknown</span>
                                            <div class="small text-muted mt-1">Date only</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $profile->birth_city }}</div>
                                        <small class="text-muted">
                                            {{ implode(', ', array_filter([$profile->birth_state, $profile->birth_country])) }}
                                        </small>
                                    </td>
                                    <td>
                                        <div class="small font-monospace">{{ number_format($profile->latitude, 4) }}°, {{ number_format($profile->longitude, 4) }}°</div>
                                        <small class="text-muted">{{ $profile->timezone }}</small>
                                    </td>
                                    <td>
                                        @if ($profile->can_calculate_exact_chart)
                                            <span class="badge bg-success" title="Eligible for exact Kundli calculation">
                                                <i class="fas fa-check-circle me-1"></i>Exact Chart OK
                                            </span>
                                        @else
                                            <span class="badge bg-secondary" title="Precision safeguards prevent exact Kundli calculation">
                                                <i class="fas fa-ban me-1"></i>Exact Chart Blocked
                                            </span>
                                        @endif
                                        <div class="mt-1">
                                            @if ($profile->can_calculate_lagna)
                                                <span class="badge bg-info text-dark">Lagna OK</span>
                                            @else
                                                <span class="badge bg-light text-muted border">Lagna Blocked</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-user-clock fa-2x mb-2 text-secondary"></i>
                    <p class="mb-0">No astrology profile registered for this user yet.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
