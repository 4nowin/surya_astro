@extends('admin.layouts.admin')

@section('title', '| Review Application #' . $application->id)

@section('content')

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2>Review Listing Application #{{ $application->id }}</h2>
        <p class="text-muted mb-0">Practitioner: {{ $application->admin->name ?? 'Admin #' . $application->admin_id }} ({{ $application->admin->email ?? '' }})</p>
    </div>
    <div>
        <a href="{{ route('admin.astrologer-applications.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Back to Applications
        </a>
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

<!-- Status Banner -->
<div class="card mb-4 border-start border-4 @if($application->status === 'approved') border-success @elseif($application->status === 'rejected') border-danger @else border-warning @endif">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">
                    Application Status: 
                    @if($application->status === 'approved')
                        <span class="badge bg-success">Approved & Published</span>
                    @elseif($application->status === 'rejected')
                        <span class="badge bg-danger">Rejected</span>
                    @else
                        <span class="badge bg-warning text-dark">Pending Verification</span>
                    @endif
                </h5>
                <p class="text-muted small mb-0">
                    Submitted: {{ $application->submitted_at ? $application->submitted_at->format('M d, Y H:i:s') : $application->created_at->format('M d, Y H:i:s') }}
                    @if($application->reviewed_at)
                        | Reviewed by: {{ $application->reviewer->name ?? 'Administrator' }} on {{ $application->reviewed_at->format('M d, Y H:i:s') }}
                    @endif
                </p>
                @if($application->status === 'rejected' && $application->rejection_reason)
                    <div class="alert alert-warning mt-2 mb-0 py-2">
                        <strong>Rejection Reason given to practitioner:</strong> {{ $application->rejection_reason }}
                    </div>
                @endif
            </div>
            <div class="text-end">
                @if($application->image)
                    <img src="{{ $application->image }}" alt="Profile Photo" width="80" height="80" class="rounded-circle object-fit-cover border">
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- English Profile Content -->
    <div class="col-md-6 mb-4">
        <div class="card h-100 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fa fa-language me-1"></i> English Listing Content</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">PRACTITIONER NAME (EN)</label>
                    <p class="h6">{{ $application->en_name }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">SPECIALISATIONS / EXPERTISE</label>
                    <p>{{ $application->en_expertise }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">LANGUAGES SPOKEN</label>
                    <p>{{ $application->en_languages }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">EXCERPT (SHORT BIO)</label>
                    <p class="text-muted">{{ $application->en_excerpt ?? 'None provided.' }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">FULL PROFILE DESCRIPTION</label>
                    <div class="p-3 bg-light rounded small" style="white-space: pre-wrap;">{{ $application->en_description ?? 'None provided.' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hindi Profile Content -->
    <div class="col-md-6 mb-4">
        <div class="card h-100 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0"><i class="fa fa-om me-1"></i> Hindi Listing Content (हिन्दी)</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">PRACTITIONER NAME (HI)</label>
                    <p class="h6">{{ $application->hi_name }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">SPECIALISATIONS / EXPERTISE (HI)</label>
                    <p>{{ $application->hi_expertise }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">LANGUAGES SPOKEN (HI)</label>
                    <p>{{ $application->hi_languages }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">EXCERPT (SHORT BIO) (HI)</label>
                    <p class="text-muted">{{ $application->hi_excerpt ?? 'उपलब्ध नहीं है।' }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">FULL PROFILE DESCRIPTION (HI)</label>
                    <div class="p-3 bg-light rounded small" style="white-space: pre-wrap;">{{ $application->hi_description ?? 'उपलब्ध नहीं है।' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shared Configuration & Pricing -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Experience & Astro Setu Pricing Configuration</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <label class="form-label text-muted small fw-bold">EXPERIENCE</label>
                <p class="h6">{{ $application->experience }}</p>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label text-muted small fw-bold">CHAT PRICE (₹ / MIN)</label>
                <p class="h6 text-primary">₹{{ $application->price }}</p>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label text-muted small fw-bold">CALL PRICE (₹ / MIN)</label>
                <p class="h6 text-success">₹{{ $application->call_price }}</p>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label text-muted small fw-bold">ORIGINAL / STRIKE PRICE (₹)</label>
                <p class="h6 text-muted">₹{{ $application->original_price }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Current Published Astro Setu Listings -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Current Published Records in Astro Setu (astrologers table)</h5>
        <span class="badge {{ $publishedListings->where('active', 1)->isNotEmpty() ? 'bg-success' : 'bg-secondary' }}">
            {{ $publishedListings->where('active', 1)->count() }} Active Published Rows
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>Row ID</th>
                    <th>Language</th>
                    <th>Name</th>
                    <th>Active</th>
                    <th>Status</th>
                    <th>Price (Chat/Call)</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($publishedListings as $pub)
                <tr>
                    <td>{{ $pub->id }}</td>
                    <td><span class="badge bg-info text-dark">{{ strtoupper($pub->language) }}</span></td>
                    <td>{{ $pub->name }}</td>
                    <td>
                        @if($pub->active)
                            <span class="badge bg-success">Active (1)</span>
                        @else
                            <span class="badge bg-secondary">Inactive (0)</span>
                        @endif
                    </td>
                    <td><span class="badge bg-light text-dark">{{ $pub->status }}</span></td>
                    <td>₹{{ $pub->price }} / ₹{{ $pub->call_price }}</td>
                    <td>{{ $pub->updated_at->format('M d, Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-3 text-muted">
                        No published astrologer records exist yet for this practitioner.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Administrator Approval & Rejection Actions -->
<div class="card border-dark mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="card-title mb-0"><i class="fa fa-gavel me-1"></i> Administrator Review Actions</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Approve Form -->
            <div class="col-md-6 mb-3">
                <div class="p-3 border rounded h-100 bg-light">
                    <h6 class="text-success fw-bold"><i class="fa fa-check-circle me-1"></i> Approve Application</h6>
                    <p class="small text-muted">
                        Approving this application will automatically create or update <strong>both English and Hindi rows</strong> in the published <code>astrologers</code> table under the practitioner's <code>admin_id</code>, setting them to active status for consumer app visibility.
                    </p>
                    <form action="{{ route('admin.astrologer-applications.approve', $application->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to approve this application and publish the listings to Astro Setu?');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check me-1"></i> Approve & Publish to Astro Setu
                        </button>
                    </form>
                </div>
            </div>

            <!-- Reject Form -->
            <div class="col-md-6 mb-3">
                <div class="p-3 border rounded h-100 bg-light">
                    <h6 class="text-danger fw-bold"><i class="fa fa-times-circle me-1"></i> Reject Application</h6>
                    <p class="small text-muted">
                        Rejecting will keep/set the practitioner's published listings inactive. The practitioner will receive the rejection feedback and be allowed to make adjustments and resubmit.
                    </p>
                    <form action="{{ route('admin.astrologer-applications.reject', $application->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to reject this application?');">
                        @csrf
                        <div class="mb-2">
                            <label for="rejection_reason" class="form-label small fw-bold">Rejection Reason / Feedback</label>
                            <textarea name="rejection_reason" id="rejection_reason" class="form-control form-control-sm" rows="3" required placeholder="Please provide specific feedback to the practitioner on why the application was not approved (e.g. invalid photo, insufficient experience details)...">{{ old('rejection_reason', $application->rejection_reason) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fa fa-times me-1"></i> Reject Application
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
