@extends('admin.layouts.admin')

@section('title', '| Service Monetization Review & Settlements')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2>Service Monetization Review & Adjudication</h2>
        <p class="text-muted mb-0">Platform administrative oversight for disputed deliverable orders, late cancellations, and no-show claims.</p>
    </div>
    <div>
        @if($counts['total_pending'] > 0)
            <span class="badge bg-danger p-2 fs-6">
                <i class="fas fa-exclamation-triangle me-1"></i> {{ $counts['total_pending'] }} Action(s) Required
            </span>
        @else
            <span class="badge bg-success p-2 fs-6">
                <i class="fas fa-check-circle me-1"></i> Review Queue Clear
            </span>
        @endif
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

<!-- Navigation Tabs -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white pb-0 border-bottom">
        <ul class="nav nav-tabs card-header-tabs" id="monetizationTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'disputed_orders' ? 'active fw-bold' : '' }}" href="{{ route('admin.monetization.index', ['tab' => 'disputed_orders']) }}">
                    <i class="fas fa-file-shield text-danger me-1"></i> Disputed Orders
                    <span class="badge {{ $counts['disputed_orders'] > 0 ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $counts['disputed_orders'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'late_cancellations' ? 'active fw-bold' : '' }}" href="{{ route('admin.monetization.index', ['tab' => 'late_cancellations']) }}">
                    <i class="fas fa-calendar-xmark text-warning me-1"></i> Late Cancellations (&lt;12h)
                    <span class="badge {{ $counts['late_cancellations'] > 0 ? 'bg-warning text-dark' : 'bg-secondary' }} ms-1">{{ $counts['late_cancellations'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'no_shows' ? 'active fw-bold' : '' }}" href="{{ route('admin.monetization.index', ['tab' => 'no_shows']) }}">
                    <i class="fas fa-user-slash text-danger me-1"></i> No-Show Reports
                    <span class="badge {{ $counts['no_shows'] > 0 ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $counts['no_shows'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'resolved_history' ? 'active fw-bold' : '' }}" href="{{ route('admin.monetization.index', ['tab' => 'resolved_history']) }}">
                    <i class="fas fa-clock-rotate-left text-primary me-1"></i> Resolution History
                    <span class="badge bg-light text-dark ms-1">{{ $counts['resolved_orders'] + $counts['resolved_appointments'] }}</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        @if($tab === 'disputed_orders')
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Astrologer</th>
                            <th>Service</th>
                            <th>Amount (INR)</th>
                            <th>Dispute Reason</th>
                            <th>Submitted At</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($disputedOrders as $order)
                            <tr>
                                <td>
                                    <span class="fw-bold">{{ $order->order_number ?? '#' . $order->id }}</span>
                                    <br><small class="text-muted">{{ ucfirst($order->order_type ?? 'deliverable') }}</small>
                                </td>
                                <td>
                                    <strong>{{ $order->user->name ?? 'User #' . $order->user_id }}</strong>
                                    <br><small class="text-muted">{{ $order->user->email ?? $order->user->mobile_no ?? '-' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $order->professional->astrologer->name ?? $order->professional->name ?? 'Practitioner #' . $order->admin_id }}</strong>
                                </td>
                                <td>
                                    {{ $order->service->title ?? 'Custom Consultation' }}
                                </td>
                                <td>
                                    <span class="fw-bold text-success">₹{{ number_format((float) $order->customer_price, 2) }}</span>
                                    <br><small class="text-muted">Base: ₹{{ number_format((float) $order->base_price, 2) }}</small>
                                </td>
                                <td>
                                    <span class="text-danger small">{{ \Illuminate\Support\Str::limit($order->dispute_reason ?? 'Customer requested refund review.', 60) }}</span>
                                </td>
                                <td>
                                    {{ $order->created_at ? $order->created_at->format('d M Y, h:i A') : '-' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.monetization.orders.show', $order->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-gavel me-1"></i> Adjudicate
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-circle-check fs-2 text-success d-block mb-2"></i>
                                    No deliverable order disputes pending review.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($disputedOrders->hasPages())
                <div class="p-3 border-top">
                    {{ $disputedOrders->links() }}
                </div>
            @endif

        @elseif($tab === 'late_cancellations')
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Appointment #</th>
                            <th>Customer</th>
                            <th>Astrologer</th>
                            <th>Scheduled Time</th>
                            <th>Customer Price</th>
                            <th>Cancellation Reason</th>
                            <th>Cancelled At</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lateCancellations as $appt)
                            <tr>
                                <td>
                                    <span class="fw-bold">#{{ $appt->id }}</span>
                                    <br><span class="badge bg-warning text-dark">&lt;12h Cutoff</span>
                                </td>
                                <td>
                                    <strong>{{ $appt->user->name ?? 'User #' . $appt->user_id }}</strong>
                                    <br><small class="text-muted">{{ $appt->user->mobile_no ?? $appt->user->email ?? '-' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $appt->professional->astrologer->name ?? $appt->professional->name ?? 'Practitioner #' . $appt->admin_id }}</strong>
                                </td>
                                <td>
                                    {{ $appt->start_at ? \Carbon\Carbon::parse($appt->start_at)->format('d M Y, h:i A') : '-' }}
                                </td>
                                <td>
                                    <span class="fw-bold text-success">₹{{ number_format((float) $appt->customer_price, 2) }}</span>
                                    <br><small class="text-muted">Base: ₹{{ number_format((float) $appt->base_price, 2) }}</small>
                                </td>
                                <td>
                                    <span class="text-warning text-dark small">{{ \Illuminate\Support\Str::limit($appt->cancellation_reason ?? 'Late cancellation by customer.', 60) }}</span>
                                </td>
                                <td>
                                    {{ $appt->cancelled_at ? \Carbon\Carbon::parse($appt->cancelled_at)->format('d M Y, h:i A') : '-' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.monetization.appointments.show', $appt->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-gavel me-1"></i> Adjudicate
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-circle-check fs-2 text-success d-block mb-2"></i>
                                    No late cancellations pending review.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($lateCancellations->hasPages())
                <div class="p-3 border-top">
                    {{ $lateCancellations->links() }}
                </div>
            @endif

        @elseif($tab === 'no_shows')
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Appointment #</th>
                            <th>Customer</th>
                            <th>Astrologer</th>
                            <th>Scheduled Time</th>
                            <th>Customer Price</th>
                            <th>Reported By</th>
                            <th>Notes</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($noShows as $appt)
                            <tr>
                                <td>
                                    <span class="fw-bold">#{{ $appt->id }}</span>
                                    <br><span class="badge bg-danger">No-Show</span>
                                </td>
                                <td>
                                    <strong>{{ $appt->user->name ?? 'User #' . $appt->user_id }}</strong>
                                    <br><small class="text-muted">{{ $appt->user->mobile_no ?? $appt->user->email ?? '-' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $appt->professional->astrologer->name ?? $appt->professional->name ?? 'Practitioner #' . $appt->admin_id }}</strong>
                                </td>
                                <td>
                                    {{ $appt->start_at ? \Carbon\Carbon::parse($appt->start_at)->format('d M Y, h:i A') : '-' }}
                                </td>
                                <td>
                                    <span class="fw-bold text-success">₹{{ number_format((float) $appt->customer_price, 2) }}</span>
                                    <br><small class="text-muted">Base: ₹{{ number_format((float) $appt->base_price, 2) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ ucfirst($appt->no_show_reported_by ?? 'user') }}</span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ \Illuminate\Support\Str::limit($appt->no_show_notes ?? '-', 60) }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.monetization.appointments.show', $appt->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-gavel me-1"></i> Adjudicate
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-circle-check fs-2 text-success d-block mb-2"></i>
                                    No no-show claims pending review.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($noShows->hasPages())
                <div class="p-3 border-top">
                    {{ $noShows->links() }}
                </div>
            @endif

        @elseif($tab === 'resolved_history')
            <div class="p-3">
                <h5 class="mb-3 text-secondary"><i class="fas fa-file-circle-check me-2"></i>Recently Resolved Deliverable Orders</h5>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Astrologer</th>
                                <th>Amount</th>
                                <th>Resolution</th>
                                <th>Resolved By</th>
                                <th>Resolved At</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resolvedOrders as $order)
                                <tr>
                                    <td><strong>{{ $order->order_number ?? '#' . $order->id }}</strong></td>
                                    <td>{{ $order->user->name ?? 'User #' . $order->user_id }}</td>
                                    <td>{{ $order->professional->astrologer->name ?? 'Practitioner' }}</td>
                                    <td>₹{{ number_format((float) $order->customer_price, 2) }}</td>
                                    <td>
                                        @if($order->dispute_status === 'resolved_refund')
                                            <span class="badge bg-warning text-dark">Refunded</span>
                                        @elseif($order->dispute_status === 'resolved_payout')
                                            <span class="badge bg-success">Paid Out</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $order->status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $order->resolver->name ?? 'Admin #' . $order->resolved_by_admin_id }}</td>
                                    <td>{{ $order->resolved_at ? \Carbon\Carbon::parse($order->resolved_at)->format('d M Y, h:i A') : '-' }}</td>
                                    <td><small class="text-muted">{{ $order->resolution_notes }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-3 text-muted">No resolved order disputes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h5 class="mb-3 text-secondary"><i class="fas fa-calendar-check me-2"></i>Recently Resolved Appointments</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Appt #</th>
                                <th>Customer</th>
                                <th>Astrologer</th>
                                <th>Amount</th>
                                <th>Resolution Type</th>
                                <th>Resolved By</th>
                                <th>Resolved At</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resolvedAppointments as $appt)
                                <tr>
                                    <td><strong>#{{ $appt->id }}</strong></td>
                                    <td>{{ $appt->user->name ?? 'User #' . $appt->user_id }}</td>
                                    <td>{{ $appt->professional->astrologer->name ?? 'Practitioner' }}</td>
                                    <td>₹{{ number_format((float) $appt->customer_price, 2) }}</td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            {{ $appt->cancellation_review_status ?? $appt->no_show_review_status ?? $appt->status }}
                                        </span>
                                    </td>
                                    <td>{{ $appt->resolver->name ?? 'Admin #' . $appt->resolved_by_admin_id }}</td>
                                    <td>{{ $appt->resolved_at ? \Carbon\Carbon::parse($appt->resolved_at)->format('d M Y, h:i A') : '-' }}</td>
                                    <td><small class="text-muted">{{ $appt->resolution_notes }}</small></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-3 text-muted">No resolved appointment claims.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
