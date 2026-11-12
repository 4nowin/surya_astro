<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $profile->display_name }} | Pandit Setu Professional</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">
    <!-- Header Banner -->
    <header class="bg-gradient-to-r from-amber-700 via-orange-600 to-amber-800 text-white shadow-md">
        <div class="max-w-4xl mx-auto px-4 py-8">
            <div class="flex flex-col sm:flex-row items-center gap-6">
                @if($profile->profile_photo_url)
                    <img src="{{ $profile->profile_photo_url }}" alt="{{ $profile->display_name }}" class="w-24 h-24 rounded-full border-4 border-white/30 object-cover shadow-lg">
                @else
                    <div class="w-24 h-24 rounded-full border-4 border-white/30 bg-white/20 flex items-center justify-center text-3xl font-bold text-white shadow-lg">
                        {{ strtoupper(substr($profile->display_name, 0, 1)) }}
                    </div>
                @endif
                <div class="text-center sm:text-left flex-1">
                    <div class="flex items-center justify-center sm:justify-start gap-2">
                        <h1 class="text-2xl sm:text-3xl font-bold">{{ $profile->display_name }}</h1>
                        <span class="bg-amber-400 text-amber-950 text-xs px-2 py-0.5 rounded-full font-semibold">Verified</span>
                    </div>
                    <p class="text-amber-100 text-base mt-1">{{ $profile->headline ?: 'Professional Spiritual Consultant' }}</p>
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mt-3 text-xs text-amber-100">
                        @if($profile->city)
                            <span class="flex items-center gap-1">📍 {{ $profile->city }}</span>
                        @endif
                        @if($profile->years_of_experience)
                            <span>⏳ {{ $profile->years_of_experience }}+ Years Exp.</span>
                        @endif
                        @if(!empty($profile->languages))
                            <span>🗣️ {{ implode(', ', $profile->languages) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8 space-y-8">
        @if(session('error'))
            <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-medium">
                {{ session('error') }}
            </div>
        @endif

        <!-- Consultation Modes & Bio -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80">
            <h2 class="text-lg font-bold text-slate-900 mb-3">About Practitioner</h2>
            <p class="text-slate-600 leading-relaxed text-sm whitespace-pre-line">{{ $profile->about ?: 'Dedicated spiritual and Vedic consultant offering guidance, rituals, and consultations.' }}</p>

            @if(!empty($profile->specializations))
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Specializations</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($profile->specializations as $spec)
                            <span class="bg-orange-50 text-orange-800 border border-orange-200 text-xs px-3 py-1 rounded-lg font-medium">{{ $spec }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 gap-2 text-center text-xs">
                <div class="p-2 rounded-lg {{ $profile->is_online_available ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-50 text-slate-400' }}">
                    <span class="font-semibold block">{{ $profile->is_online_available ? '✓ Online Available' : '✕ No Online' }}</span>
                </div>
                <div class="p-2 rounded-lg {{ $profile->is_in_person_available ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-50 text-slate-400' }}">
                    <span class="font-semibold block">{{ $profile->is_in_person_available ? '✓ In-Person Office' : '✕ No In-Person' }}</span>
                </div>
                <div class="p-2 rounded-lg {{ $profile->is_home_visit_available ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-50 text-slate-400' }}">
                    <span class="font-semibold block">{{ $profile->is_home_visit_available ? '✓ Home / Site Visits' : '✕ No Home Visits' }}</span>
                </div>
            </div>
        </div>

        <!-- Available Services & Appointment Booking -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Services & Public Booking</h2>
                    <p class="text-xs text-slate-500">Select a service to request an appointment slot.</p>
                </div>
                <span class="bg-emerald-100 text-emerald-800 text-xs px-2.5 py-1 rounded-full font-semibold">Booking Open</span>
            </div>

            @if($services->isEmpty())
                <p class="text-slate-500 text-sm py-8 text-center">No public services currently listed for booking.</p>
            @else
                <form action="{{ route('public.booking.submit', $profile->slug) }}" method="POST" class="space-y-6">
                    @csrf

                    <!-- Services Radio List -->
                    <div class="space-y-3">
                        <label class="block text-sm font-semibold text-slate-700">1. Select Service *</label>
                        <div class="grid sm:grid-cols-2 gap-3">
                            @foreach($services as $idx => $svc)
                                <label class="border border-slate-200 rounded-xl p-4 flex flex-col justify-between cursor-pointer hover:border-orange-500 hover:bg-orange-50/20 transition-all has-[:checked]:border-orange-600 has-[:checked]:bg-orange-50/30">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" name="service_id" value="{{ $svc->id }}" class="text-orange-600 focus:ring-orange-500" {{ $loop->first ? 'checked' : '' }}>
                                            <span class="font-bold text-slate-800 text-sm">{{ $svc->name }}</span>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2 line-clamp-2">{{ $svc->public_description ?: $svc->description }}</p>
                                    <div class="mt-3 flex items-center justify-between text-xs font-semibold text-slate-700 border-t border-slate-100 pt-2">
                                        <span>⏱️ {{ $svc->duration_minutes }} mins</span>
                                        <span class="text-orange-700 font-bold">
                                            {{ $svc->currency == 'INR' ? '₹' : $svc->currency . ' ' }}{{ number_format($svc->price, 0) }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Date & Time Selection -->
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">2. Preferred Date *</label>
                            <input type="date" name="requested_date" value="{{ date('Y-m-d', strtotime('+1 day')) }}" min="{{ date('Y-m-d') }}" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Preferred Time *</label>
                            <input type="time" name="requested_time" value="10:00" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none" required>
                        </div>
                    </div>

                    <!-- Delivery Method -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">3. Consultation Mode *</label>
                        <select name="delivery_method" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none">
                            <option value="online">Online Video / Audio Call</option>
                            <option value="phone_call">Phone Consultation</option>
                            @if($profile->is_in_person_available)
                                <option value="in_person">In-Person at Practitioner Office</option>
                            @endif
                            @if($profile->is_home_visit_available)
                                <option value="home_visit">Home Visit / Puja at My Location</option>
                            @endif
                        </select>
                    </div>

                    <!-- Client Details -->
                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">4. Your Contact Details</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Full Name *</label>
                                <input type="text" name="client_name" placeholder="e.g. Rahul Sharma" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Mobile Phone (WhatsApp) *</label>
                                <input type="tel" name="client_phone" placeholder="e.g. 9876543210" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none" required>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Email Address {{ $profile->is_email_mandatory ? '*' : '(Optional)' }}</label>
                                <input type="email" name="client_email" placeholder="rahul@example.com" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none" {{ $profile->is_email_mandatory ? 'required' : '' }}>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">Address / Location (If Home Visit)</label>
                                <input type="text" name="location" placeholder="City or Area" class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Note or Message for Practitioner (Optional)</label>
                            <textarea name="client_message" rows="2" placeholder="Briefly describe what you'd like guidance or puja for..." class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-500 outline-none"></textarea>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full sm:w-auto px-8 py-3.5 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all text-sm">
                            Submit Appointment Request →
                        </button>
                        <p class="text-xs text-slate-400 mt-2">No advance online payment required now. The practitioner will review and confirm your slot.</p>
                    </div>
                </form>
            @endif
        </div>
    </main>

    <footer class="border-t border-slate-200 mt-16 py-6 text-center text-xs text-slate-400">
        Powered by <strong class="text-slate-600">Pandit Setu</strong> &bull; Professional Spiritual Practice Platform
    </footer>
</body>
</html>
