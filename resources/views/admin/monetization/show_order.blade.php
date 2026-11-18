@extends('admin.layouts.admin')

@section('title', '| Order Dispute #' . ($order->order_number ?? $order->id))

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.monetization.index', ['tab' => 'disputed_orders']) }}" class="btn btn-outline-secondary btn-sm mb-2">
        <i class="fas fa-arrow-left me-1"></i> Back to Review Queue
    </a>
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h2>Order Dispute: {{ $order->order_number ?? '#' . $order->id }}</h2>
            <p class="text-muted mb-0">Adjudicate deliverable report order between customer and astrologer.</p>
        </div>
        <div>
            @if($order->status === 'disputed')
                <span class="badge bg-danger fs-6 p-2">Pending Administrative Review</span>
            @elseif($order->dispute_status === 'resolved_refund')
                <span class="badge bg-warning text-dark fs-6 p-2">Resolved: Refunded to Customer</span>
            @elseif($order->dispute_status === 'resolved_payout')
                <span class="badge bg-success fs-6 p-2">Resolved: Released to Astrologer</span>
            @else
                <span class="badge bg-secondary fs-6 p-2">{{ ucfirst($order->status) }}</span>
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
                        <td class="text-end fw-bold">₹{{ number_format((float) $order->base_price, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Platform Markup (20%):</td>
                        <td class="text-end fw-bold text-info">₹{{ number_format((float) $order->platform_fee, 2) }}</td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Total Customer Paid:</td>
                        <td class="text-end fw-bold text-success fs-5">₹{{ number_format((float) $order->customer_price, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Escrow Transaction Status:</td>
                        <td class="text-end">
                            <span class="badge {{ $order->escrowTransaction?->payment_status === 'completed' ? 'bg-success' : ($order->escrowTransaction?->payment_status === 'refunded' ? 'bg-warning text-dark' : 'bg-primary') }}">
                                {{ ucfirst($order->escrowTransaction?->payment_status ?? 'pending') }}
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
                <p class="mb-1"><strong>Name:</strong> {{ $order->user->name ?? 'User #' . $order->user_id }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $order->user->email ?? '-' }}</p>
                <p class="mb-1"><strong>Mobile:</strong> {{ $order->user->mobile_no ?? '-' }}</p>
                <p class="mb-0"><strong>Current Wallet Balance:</strong> ₹{{ number_format((float) ($order->user->wallet_balance ?? 0), 2) }}</p>
            </div>
        </div>

        <!-- Astrologer Card -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-star text-warning me-2"></i>Practitioner Information
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Name:</strong> {{ $order->professional->astrologer->name ?? $order->professional->name ?? 'Practitioner' }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ $order->professional->email ?? '-' }}</p>
                <p class="mb-0"><strong>Mobile:</strong> {{ $order->professional->astrologer->mobile_no ?? '-' }}</p>
            </div>
        </div>
    </div>

    <!-- Right Column: Order Content, Dispute Evidence, & Adjudication -->
    <div class="col-lg-7">
        <!-- Service & Deliverable Info -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-file-lines text-info me-2"></i>Service & Deliverable Content
            </div>
            <div class="card-body">
                <h5>{{ $order->service->title ?? 'Astrology Service' }}</h5>
                <p class="text-muted small mb-3">{{ $order->service->description ?? '' }}</p>

                <div class="mb-3 p-3 bg-light rounded">
                    <strong class="d-block mb-1 text-secondary">Customer Intake Requirements:</strong>
                    <div>{{ $order->notes ?? 'No special intake notes provided.' }}</div>
                </div>

                <div class="mb-3 p-3 bg-light rounded">
                    <strong class="d-block mb-1 text-secondary">Delivered Content / Report:</strong>
                    @if(!empty($order->deliverable_text))
                        <div class="text-pre-wrap">{{ $order->deliverable_text }}</div>
                    @else
                        <span class="text-muted fst-italic">No textual report submitted.</span>
                    @endif

                    @if(!empty($order->deliverable_file_url))
                        <div class="mt-2">
                            <a href="{{ $order->deliverable_file_url }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-paperclip me-1"></i> View Deliverable Attachment
                            </a>
                        </div>
                    @endif
                </div>

                <div class="row text-muted small">
                    <div class="col-sm-6">
                        <strong>Ordered At:</strong> {{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '-' }}
                    </div>
                    <div class="col-sm-6">
                        <strong>Delivered At:</strong> {{ $order->delivered_at ? \Carbon\Carbon::parse($order->delivered_at)->format('d M Y, h:i A') : '-' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispute Details -->
        <div class="card mb-4 shadow-sm border-danger border-opacity-25">
            <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold">
                <i class="fas fa-triangle-exclamation me-2"></i>Dispute Reason Stated by Customer
            </div>
            <div class="card-body">
                <p class="fs-6 mb-2">{{ $order->dispute_reason ?? 'Customer rejected deliverable and requested refund review.' }}</p>
                <small class="text-muted">Disputed on: {{ $order->disputed_at ? \Carbon\Carbon::parse($order->disputed_at)->format('d M Y, h:i A') : ($order->updated_at ? $order->updated_at->format('d M Y, h:i A') : '-') }}</small>
            </div>
        </div>

        <!-- Adjudication Action Box -->
        @if($order->status === 'disputed')
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fas fa-gavel me-2"></i>Administrative Adjudication
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.monetization.orders.resolve', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to execute this irreversible financial adjudication?');">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Resolution Decision <span class="text-danger">*</span></label>
                            <div class="form-check p-3 border rounded mb-2">
                                <input class="form-check-input" type="radio" name="resolution" id="res_refund" value="refund" required>
                                <label class="form-check-label ms-2" for="res_refund">
                                    <strong class="text-danger"><i class="fas fa-rotate-left me-1"></i> Issue Full Wallet Refund to Customer</strong>
                                    <br><small class="text-muted">Refunds ₹{{ number_format((float) $order->customer_price, 2) }} back to customer's wallet. Cancels astrologer escrow (₹0 earnings). Marks order as cancelled.</small>
                                </label>
                            </div>
                            <div class="form-check p-3 border rounded">
                                <input class="form-check-input" type="radio" name="resolution" id="res_payout" value="payout" required>
                                <label class="form-check-label ms-2" for="res_payout">
                                    <strong class="text-success"><i class="fas fa-check-double me-1"></i> Release Payout to Astrologer</strong>
                                    <br><small class="text-muted">Releases base price ₹{{ number_format((float) $order->base_price, 2) }} from escrow to astrologer's earnings. Platform retains 20% markup. Marks order as completed.</small>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label fw-bold">Administrative Adjudication Notes <span class="text-danger">*</span></label>
                            <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Provide clear reasoning for this settlement decision..." required minlength="5" maxlength="1000">{{ old('notes') }}</textarea>
                            <small class="text-muted">This note will be recorded permanently in the financial audit trail.</small>
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
                    <p class="mb-1"><strong>Decision:</strong> {{ $order->dispute_status === 'resolved_refund' ? 'Full Refund to Customer' : 'Earnings Released to Astrologer' }}</p>
                    <p class="mb-1"><strong>Resolved By:</strong> {{ $order->resolver->name ?? 'Admin #' . $order->resolved_by_admin_id }}</p>
                    <p class="mb-1"><strong>Resolved At:</strong> {{ $order->resolved_at ? \Carbon\Carbon::parse($order->resolved_at)->format('d M Y, h:i A') : '-' }}</p>
                    <p class="mb-0"><strong>Administrative Notes:</strong> {{ $order->resolution_notes }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
