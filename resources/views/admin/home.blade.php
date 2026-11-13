@extends('admin.layouts.admin')

@section('content')
<div class="container-fluid">
    @if ($isSuperAdmin)
        {{-- Super Admin Platform Overview --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 mb-0 text-gray-800">Platform Administration Overview</h2>
                <p class="text-muted small mb-0">Super Admin oversight of practitioners, listing approvals, and consumer services.</p>
            </div>
            <div>
                <a href="{{ route('admin.astrologer-applications.index') }}" class="btn btn-primary btn-sm shadow-sm">
                    <i class="fas fa-clipboard-check me-1"></i> Review Listing Applications
                    @if($stats['pending_applications_count'] > 0)
                        <span class="badge bg-danger ms-1">{{ $stats['pending_applications_count'] }}</span>
                    @endif
                </a>
            </div>
        </div>

        {{-- Statistics Row --}}
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Practitioners</div>
                                <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['practitioners_count'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-tie fa-2x text-muted opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-warning border-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Approvals</div>
                                <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['pending_applications_count'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-muted opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-success border-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-success text-uppercase mb-1">Approved Astrologers</div>
                                <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['approved_astrologers_count'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-star fa-2x text-muted opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-info border-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <div class="text-xs fw-bold text-info text-uppercase mb-1">Consumers / Users</div>
                                <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['users_count'] }}</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-muted opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pending Applications Queue --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Pending Astro Setu Listing Applications</h6>
                        <a href="{{ route('admin.astrologer-applications.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body p-0">
                        @if ($pendingApplications->isEmpty())
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                                <p class="mb-0">No pending listing applications awaiting verification.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Practitioner Email</th>
                                            <th>Experience</th>
                                            <th>Rate (Min)</th>
                                            <th>Submitted At</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pendingApplications as $application)
                                            <tr>
                                                <td>
                                                    <div class="fw-bold">{{ $application->display_name }}</div>
                                                    <small class="text-muted">{{ $application->admin?->name }}</small>
                                                </td>
                                                <td>{{ $application->admin?->email ?? 'N/A' }}</td>
                                                <td>{{ $application->experience_years }} yrs</td>
                                                <td>₹{{ number_format($application->charge_per_minute, 2) }}</td>
                                                <td>{{ $application->created_at->format('M d, Y H:i') }}</td>
                                                <td class="text-end">
                                                    <a href="{{ route('admin.astrologer-applications.show', $application->id) }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye me-1"></i> Review
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- Practitioner Workspace Portal --}}
        <div class="mb-4">
            <h2 class="h3 mb-1 text-gray-800">Practitioner Workspace</h2>
            <p class="text-muted small">Welcome, {{ Auth::user()->name }}. Manage your practice, consultations, and Astro Setu listing status.</p>
        </div>

        {{-- Listing Status Card --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="fw-bold mb-1">Astro Setu Marketplace Status</h5>
                        @if ($publishedListing)
                            <div class="d-flex align-items-center mt-2">
                                <span class="badge bg-success me-2 px-3 py-2"><i class="fas fa-check-circle me-1"></i> Published & Active</span>
                                <span class="text-muted small">Your profile is visible on the Astro Setu consumer app.</span>
                            </div>
                            <div class="mt-3 text-secondary small">
                                <div><strong>Display Name:</strong> {{ $publishedListing->name }}</div>
                                <div><strong>Languages:</strong> {{ $publishedListing->astrologer_language ?? 'English' }}</div>
                                <div><strong>Rate:</strong> ₹{{ number_format($publishedListing->charge, 2) }}/min</div>
                            </div>
                        @elseif ($application)
                            <div class="d-flex align-items-center mt-2">
                                @if ($application->status === 'pending')
                                    <span class="badge bg-warning text-dark me-2 px-3 py-2"><i class="fas fa-clock me-1"></i> Under Review</span>
                                    <span class="text-muted small">Submitted on {{ $application->created_at->format('M d, Y') }}. Super Admin verification is in progress.</span>
                                @elseif ($application->status === 'rejected')
                                    <span class="badge bg-danger me-2 px-3 py-2"><i class="fas fa-times-circle me-1"></i> Needs Revision</span>
                                    <span class="text-muted small">Reason: {{ $application->rejection_reason ?? 'Not specified' }}</span>
                                @else
                                    <span class="badge bg-info me-2 px-3 py-2">{{ ucfirst($application->status) }}</span>
                                @endif
                            </div>
                        @else
                            <div class="mt-2 text-muted">
                                <p class="mb-0">You have not submitted an application for listing on the Astro Setu consumer marketplace yet. You can apply directly through the <strong>Pandit Setu</strong> mobile app.</p>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <span class="badge bg-light text-secondary border px-3 py-2">
                            <i class="fas fa-mobile-alt me-1"></i> Pandit Setu Practitioner
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Practitioner Workspace Metrics --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Services Offered</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['services_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-info border-4">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Jajmans (Clients)</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['jajmans_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-success border-4">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Invoices</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['invoices_count'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 py-2 border-start border-secondary border-4">
                    <div class="card-body">
                        <div class="text-xs fw-bold text-secondary text-uppercase mb-1">Appointments</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $stats['appointments_count'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm bg-light">
            <div class="card-body p-4 text-center">
                <i class="fas fa-mobile-screen-button fa-2x text-primary mb-2"></i>
                <h5 class="fw-bold">Manage Your Practice on the Go</h5>
                <p class="text-muted small mx-auto" style="max-width: 600px;">
                    Pandit Setu provides your complete mobile workspace for kundli generation, match making, jajman profiles, consultation notes, invoicing, and appointments.
                </p>
            </div>
        </div>
    @endif
</div>
@endsection
