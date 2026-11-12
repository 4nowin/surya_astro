<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Request: {{ $booking->booking_reference }} | Pandit Setu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen py-10 px-4">
    <div class="max-w-xl mx-auto space-y-6">
        <!-- Success/Status Card -->
        <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200 text-center">
            @if($booking->status === 'confirmed')
                <div class="w-16 h-16 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                    ✓
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Appointment Confirmed!</h1>
                <p class="text-sm text-slate-600 mt-1">Your session has been accepted and scheduled by {{ $profile?->display_name ?? 'the practitioner' }}.</p>
            @elseif($booking->status === 'rejected')
                <div class="w-16 h-16 bg-red-100 text-red-700 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                    ✕
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Request Declined</h1>
                <p class="text-sm text-slate-600 mt-1">The practitioner was unable to accept this booking request.</p>
                @if($booking->rejection_reason)
                    <div class="mt-4 p-3 bg-red-50 text-red-800 text-xs rounded-lg text-left">
                        <strong>Reason:</strong> {{ $booking->rejection_reason }}
                    </div>
                @endif
            @elseif($booking->status === 'alternative_proposed')
                <div class="w-16 h-16 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                    ⏱️
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Alternative Time Proposed</h1>
                <p class="text-sm text-slate-600 mt-1">The practitioner proposed an alternate slot for your consultation.</p>
                @if($booking->alternative_notes)
                    <div class="mt-4 p-3 bg-amber-50 text-amber-900 text-xs rounded-lg text-left">
                        <strong>Practitioner Note:</strong> {{ $booking->alternative_notes }}
                    </div>
                @endif
            @else
                <div class="w-16 h-16 bg-orange-100 text-orange-700 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                    ⏳
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Request Submitted</h1>
                <p class="text-sm text-slate-600 mt-1">Your appointment request is awaiting review by {{ $profile?->display_name ?? 'the practitioner' }}.</p>
            @endif

            <div class="inline-block bg-slate-100 text-slate-700 text-xs font-mono font-bold px-3 py-1.5 rounded-lg mt-4">
                Reference: {{ $booking->booking_reference }}
            </div>
        </div>

        <!-- Booking Details Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Booking Particulars</h2>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Practitioner</span>
                    <span class="font-bold text-slate-800">{{ $profile?->display_name ?? 'Practitioner' }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Service</span>
                    <span class="font-bold text-slate-800">{{ $booking->service_name_snapshot }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Requested Time</span>
                    <span class="font-bold text-slate-800">
                        {{ $booking->requested_start_at ? $booking->requested_start_at->setTimezone($booking->timezone)->format('d M Y, h:i A') : 'N/A' }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Consultation Mode</span>
                    <span class="font-bold text-slate-800 capitalize">{{ str_replace('_', ' ', $booking->delivery_method) }}</span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Estimated Fee</span>
                    <span class="font-bold text-orange-700">
                        {{ $booking->currency_snapshot == 'INR' ? '₹' : $booking->currency_snapshot . ' ' }}{{ number_format($booking->agreed_price_snapshot, 0) }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 block font-medium">Client Name</span>
                    <span class="font-bold text-slate-800">{{ $booking->client_name }}</span>
                </div>
            </div>

            @if($booking->status === 'pending')
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Bookmark this link to monitor request status.</span>
                    @if($profile)
                        <a href="{{ route('public.practitioner.profile', $profile->slug) }}" class="text-orange-600 font-semibold hover:underline">← Back to Profile</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</body>
</html>
