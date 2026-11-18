@extends('admin.layouts.admin')

@section('title', '| Appointment Adjudication #' . $appointment->id)

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.monetization.index', ['tab' => $appointment->status === 'cancelled_pending_review' ? 'late_cancellations' : 'no_shows']) }}" class="btn btn-outline-secondary btn-sm mb-2">
        <i class="fas fa-arrow-left me-1"></i> Back to Review Queue
    </a>
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h2>
                Appointment #{{ $appointment->id }}
                @if($appointment->status === 'cancelled_pending_review')
                    <span class="badge bg-warning text-dark fs-6 ms-2">Late Cancellation Review</span>
                @elseif($appointment->status === 'no_show_pending_review')
                    <span class="badge bg-danger fs-6 ms-2">No-Show Claim Review</span>
                @else
                    <span class="badge bg-secondary fs-6 ms-2">{{ ucfirst($appointment->status) }}</span>
                @endif
            </h2>
            <p class="text-muted mb-0">Adjudicate appointment scheduling disputes, late cancellation cutoffs, and attendance defaults.</p>
        </div>
        <div>
            @if($appointment->cancellation_review_status === 'resolved_refund' || $appointment->no_show_review_status === 'resolved_refund')
                <span class="badge bg-warning text-dark fs-6 p-2">Resolved: Refunded to Customer</span>
            @elseif($appointment->cancellation_review_status === 'resolved_payout' || $appointment->no_show_review_status === 'resolved_payout')
                <span class="badge bg-success fs-6 p-2">Resolved: Released to Astrologer</span>
            @endif
        </div>
    </div>
</div>

<div class="mb-3">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-circle-exclamation me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
</div>

<div class="row g-4">
    <!-- Left Column: Parties & Financial Breakdown -->
    <div class="col-lg-5">
        <!-- Financial Overview Card -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-wallet text-primary me-2"></i>Commercial Breakdown & Escrow
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm mb-0">
                    <tr>
                        <td class="text-muted">Astrologer Base Price:</td>
                        <td class="text-end fw-bold">₹{{ number_format((float) $appointment->base_price, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Platform Markup (20%):</td>
                        <td class="text-end fw-bold text-info">₹{{ number_format((float) ($appointment->customer_price - $appointment->base_price), 2) }}</td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Customer Paid:</td>
                        <td class="text-end fw-bold text-success fs-5">₹{{ number_format((float) $appointment->customer_price, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Escrow Transaction Status:</td>
                        <td class="text-end">
                            <span class="badge {{ $appointment->escrowTransaction?->payment_status === 'completed' ? 'bg-success' : ($appointment->escrowTransaction?->payment_status === 'refunded' ? 'bg-warning text-dark' : 'bg-primary') }}">
                                {{ ucfirst($appointment->escrowTransaction?->payment_status ?? 'pending') }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Customer Card -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-user text-secondary me-2"></i>Customer Information
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Name:</strong> {{ $appointment->user->name ?? 'User #' . $appointment->user_id }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $appointment->user->email ?? '-' }}</p>
                <p class="mb-1"><strong>Mobile:</strong> {{ $appointment->user->mobile_no ?? '-' }}</p>
                <p class="mb-0"><strong>Current Wallet Balance:</strong> ₹{{ number_format((float) ($appointment->user->wallet_balance ?? 0), 2) }}</p>
            </div>
        </div>

        <!-- Astrologer Card -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-star text-warning me-2"></i>Practitioner Information
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Name:</strong> {{ $appointment->professional->astrologer->name ?? $appointment->professional->name ?? 'Practitioner' }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $appointment->professional->email ?? '-' }}</p>
                <p class="mb-0"><strong>Mobile:</strong> {{ $appointment->professional->astrologer->mobile_no ?? '-' }}</p>
            </div>
        </div>
    </div>

    <!-- Right Column: Appointment Timing, Dispute Cause, & Adjudication -->
    <div class="col-lg-7">
        <!-- Appointment Scheduling Info -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-calendar-days text-info me-2"></i>Appointment Schedule & Timeline
            </div>
            <div class="card-body">
                <h5>{{ $appointment->service->title ?? 'Consultation Appointment' }}</h5>
                <p class="text-muted small mb-3">{{ $appointment->notes ?? 'Scheduled 1-on-1 session.' }}</p>

                <div class="row mb-3">
                    <div class="col-sm-6 mb-2">
                        <strong class="d-block text-secondary small">Scheduled Session Time:</strong>
                        <span class="fs-6 fw-bold">
                            {{ $appointment->start_at ? \Carbon\Carbon::parse($appointment->start_at)->format('d M Y, h:i A') : '-' }}
                        </span>
                    </div>
                    <div class="col-sm-6 mb-2">
                        <strong class="d-block text-secondary small">Session Duration:</strong>
                        <span>{{ $appointment->duration_minutes_snapshot ?? 30 }} minutes</span>
                    </div>
                    <div class="col-sm-6">
                        <strong class="d-block text-secondary small">Booked At:</strong>
                        <span>{{ $appointment->created_at ? $appointment->created_at->format('d M Y, h:i A') : '-' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <strong class="d-block text-secondary small">Confirmed By Astrologer:</strong>
                        <span>{{ $appointment->accepted_at ? \Carbon\Carbon::parse($appointment->accepted_at)->format('d M Y, h:i A') : 'N/A' }}</span>
                    </div>
                </div>

                @if($appointment->status === 'cancelled_pending_review')
                    @php
                        $start = \Carbon\Carbon::parse($appointment->start_at);
                        $cancelled = \Carbon\Carbon::parse($appointment->cancelled_at);
                        $diffHours = round($cancelled->diffInMinutes($start, false) / 60, 1);
                    @endphp
                    <div class="p-3 bg-warning bg-opacity-10 rounded border border-warning">
                        <strong class="d-block text-warning text-dark mb-1">
                            <i class="fas fa-hourglass-half me-1"></i> Late Cancellation Rule Triggered:
                        </strong>
                        <p class="mb-1 small">
                            Customer cancelled this appointment <strong>{{ $diffHours }} hours</strong> prior to the scheduled session.
                            Because this is within the <strong>12-hour cancellation cutoff</strong>, funds remain secured in escrow pending platform review.
                        </p>
                        <p class="mb-0 small text-muted">
                            <strong>Customer Reason:</strong> {{ $appointment->cancellation_reason ?? 'Not specified' }}
                        </p>
                    </div>
                @elseif($appointment->status === 'no_show_pending_review')
                    <div class="p-3 bg-danger bg-opacity-10 rounded border border-danger">
                        <strong class="d-block text-danger mb-1">
                            <i class="fas fa-user-xmark me-1"></i> No-Show Report Details:
                        </strong>
                        <p class="mb-1 small">
                            Reported By: <strong>{{ ucfirst($appointment->no_show_reported_by ?? 'user') }}</strong> on
                            {{ $appointment->no_show_reported_at ? \Carbon\Carbon::parse($appointment->no_show_reported_at)->format('d M Y, h:i A') : '-' }}
                        </p>
                        <p class="mb-0 small text-muted">
                            <strong>Report Notes:</strong> {{ $appointment->no_show_notes ?? 'No additional notes.' }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Adjudication Action Box -->
        @if($appointment->status === 'cancelled_pending_review')
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fas fa-gavel me-2"></i>Late Cancellation Adjudication
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.monetization.appointments.resolve-cancellation', $appointment->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to execute this irreversible financial adjudication?');">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Resolution Decision <span class="text-danger">*</span></label>
                            <div class="form-check p-3 border rounded mb-2">
                                <input class="form-check-input" type="radio" name="resolution" id="res_refund" value="refund" required>
                                <label class="form-check-label ms-2" for="res_refund">
                                    <strong class="text-danger"><i class="fas fa-rotate-left me-1"></i> Issue Full Wallet Refund to Customer</strong>
                                    <br><small class="text-muted">Refunds ₹{{ number_format((float) $appointment->customer_price, 2) }} to customer's wallet. Astrologer receives ₹0.</small>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded">
                                <input class="form-check-input" type="radio" name="resolution" id="res_payout" value="payout" required>
                                <label class="form-check-label ms-2" for="res_payout">
                                    <strong class="text-success"><i class="fas fa-check-double me-1"></i> Release Payout to Astrologer</strong>
                                    <br><small class="text-muted">Releases base price ₹{{ number_format((float) $appointment->base_price, 2) }} to astrologer due to late customer default. Customer receives no refund.</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label fw-bold">Administrative Adjudication Notes <span class="text-danger">*</span></label>
                            <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Provide clear reasoning for this settlement decision..." required minlength="5" maxlength="1000">{{ old('notes') }}</textarea>
                            <small class="text-muted">Recorded in the audit trail.</small>
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-shield-halved me-1"></i> Execute Final Adjudication
                        </button>
                    </form>
                </div>
            </div>
        @elseif($appointment->status === 'no_show_pending_review')
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fas fa-gavel me-2"></i>No-Show Adjudication
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.monetization.appointments.resolve-noshow', $appointment->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to execute this irreversible financial adjudication?');">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Resolution Decision <span class="text-danger">*</span></label>
                            <div class="form-check p-3 border rounded mb-2">
                                <input class="form-check-input" type="radio" name="resolution" id="res_refund" value="refund" required>
                                <label class="form-check-label ms-2" for="res_refund">
                                    <strong class="text-danger"><i class="fas fa-rotate-left me-1"></i> Issue Full Wallet Refund to Customer</strong>
                                    <br><small class="text-muted">Customer default excused (or astrologer absent). Refunds ₹{{ number_format((float) $appointment->customer_price, 2) }} to customer.</small>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded">
                                <input class="form-check-input" type="radio" name="resolution" id="res_payout" value="payout" required>
                                <label class="form-check-label ms-2" for="res_payout">
                                    <strong class="text-success"><i class="fas fa-check-double me-1"></i> Release Payout to Astrologer</strong>
                                    <br><small class="text-muted">Astrologer attended session; customer was absent. Releases ₹{{ number_format((float) $appointment->base_price, 2) }} to astrologer.</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label fw-bold">Administrative Adjudication Notes <span class="text-danger">*</span></label>
                            <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Provide clear reasoning for this settlement decision..." required minlength="5" maxlength="1000">{{ old('notes') }}</textarea>
                            <small class="text-muted">Recorded in the audit trail.</small>
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-shield-halved me-1"></i> Execute Final Adjudication
                        </button>
                    </form>
                </div>
            </div>
        @else
            <!-- Already Resolved View -->
            <div class="card shadow-sm border-0 bg-light">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-file-circle-check text-success me-2"></i>Resolution Record
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Status:</strong> {{ $appointment->cancellation_review_status ?? $appointment->no_show_review_status ?? $appointment->status }}</p>
                    <p class="mb-1"><strong>Resolved By:</strong> {{ $appointment->resolver->name ?? 'Admin #' . $appointment->resolved_by_admin_id }}</p>
                    <p class="mb-1"><strong>Resolved At:</strong> {{ $appointment->resolved_at ? \Carbon\Carbon::parse($appointment->resolved_at)->format('d M Y, h:i A') : '-' }}</p>
                    <p class="mb-0"><strong>Administrative Notes:</strong> {{ $appointment->resolution_notes }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
