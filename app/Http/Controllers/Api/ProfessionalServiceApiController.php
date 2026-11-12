<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfessionalServiceApiController extends Controller
{
    /**
     * 1. GET /api/astrologer/services
     * List authenticated practitioner's services with search, category filtering, and pagination.
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = ProfessionalService::where('admin_id', $admin->id)
            ->withCount(['appointments', 'consultations']);

        // Filter by category
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        // Filter by pricing model
        if ($model = $request->query('pricing_model')) {
            $query->where('pricing_model', $model);
        }

        // Search in name, description, specialization
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('specialization', 'like', "%{$search}%");
            });
        }

        // Check if all items requested (e.g., for appointment dropdown)
        if ($request->boolean('all')) {
            $services = $query->orderBy('name', 'asc')->get();
            return response()->json([
                'success' => true,
                'data' => $services,
            ]);
        }

        $perPage = (int) $request->query('per_page', 20);
        $services = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $services->items(),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'total' => $services->total(),
                'per_page' => $services->perPage(),
            ],
        ]);
    }

    /**
     * 2. POST /api/astrologer/services
     * Create a new professional service with ownership attached to the authenticated user.
     */
    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'category' => 'required|string|max:64',
            'specialization' => 'nullable|string|max:191',
            'pricing_model' => ['required', Rule::in(['fixed', 'hourly', 'starting_from', 'custom'])],
            'price' => [
                Rule::requiredIf(fn () => in_array($request->input('pricing_model'), ['fixed', 'hourly', 'starting_from'])),
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => 'nullable|string|max:8',
            'duration_minutes' => 'required|integer|min:5|max:1440',
            'delivery_methods' => 'required|array|min:1',
            'delivery_methods.*' => ['string', Rule::in(['online', 'phone_call', 'in_person', 'home_visit', 'at_location', 'custom'])],
            'is_active' => 'nullable|boolean',
            'preparation_instructions' => 'nullable|string',
            'additional_info' => 'nullable|string',
        ]);

        $service = ProfessionalService::create([
            'admin_id' => $admin->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'],
            'specialization' => $validated['specialization'] ?? null,
            'pricing_model' => $validated['pricing_model'],
            'price' => $validated['pricing_model'] === 'custom' ? null : ($validated['price'] ?? null),
            'currency' => $validated['currency'] ?? 'INR',
            'duration_minutes' => $validated['duration_minutes'],
            'delivery_methods' => $validated['delivery_methods'],
            'is_active' => $validated['is_active'] ?? true,
            'preparation_instructions' => $validated['preparation_instructions'] ?? null,
            'additional_info' => $validated['additional_info'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully.',
            'data' => $service,
        ], 201);
    }

    /**
     * 3. GET /api/astrologer/services/{id}
     * Retrieve single service details with ownership enforcement.
     */
    public function show(Request $request, $id)
    {
        $admin = $request->user();

        $service = ProfessionalService::where('admin_id', $admin->id)
            ->withCount(['appointments', 'consultations'])
            ->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found in your catalogue.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    /**
     * 4. PUT/PATCH /api/astrologer/services/{id}
     * Update an existing service with ownership enforcement.
     */
    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $service = ProfessionalService::where('admin_id', $admin->id)->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found in your catalogue.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|string|max:64',
            'specialization' => 'nullable|string|max:191',
            'pricing_model' => ['sometimes', 'required', Rule::in(['fixed', 'hourly', 'starting_from', 'custom'])],
            'price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => 'nullable|string|max:8',
            'duration_minutes' => 'sometimes|required|integer|min:5|max:1440',
            'delivery_methods' => 'sometimes|required|array|min:1',
            'delivery_methods.*' => ['string', Rule::in(['online', 'phone_call', 'in_person', 'home_visit', 'at_location', 'custom'])],
            'is_active' => 'nullable|boolean',
            'preparation_instructions' => 'nullable|string',
            'additional_info' => 'nullable|string',
        ]);

        $pricingModel = $validated['pricing_model'] ?? $service->pricing_model;
        if (in_array($pricingModel, ['fixed', 'hourly', 'starting_from']) && !array_key_exists('price', $validated) && $service->price === null) {
            return response()->json([
                'success' => false,
                'message' => "A price amount is required for {$pricingModel} pricing model.",
            ], 422);
        }

        $service->update([
            'name' => $validated['name'] ?? $service->name,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $service->description,
            'category' => $validated['category'] ?? $service->category,
            'specialization' => array_key_exists('specialization', $validated) ? $validated['specialization'] : $service->specialization,
            'pricing_model' => $pricingModel,
            'price' => $pricingModel === 'custom' ? null : (array_key_exists('price', $validated) ? $validated['price'] : $service->price),
            'currency' => $validated['currency'] ?? $service->currency,
            'duration_minutes' => $validated['duration_minutes'] ?? $service->duration_minutes,
            'delivery_methods' => $validated['delivery_methods'] ?? $service->delivery_methods,
            'is_active' => array_key_exists('is_active', $validated) ? (bool)$validated['is_active'] : $service->is_active,
            'preparation_instructions' => array_key_exists('preparation_instructions', $validated) ? $validated['preparation_instructions'] : $service->preparation_instructions,
            'additional_info' => array_key_exists('additional_info', $validated) ? $validated['additional_info'] : $service->additional_info,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => $service->fresh(),
        ]);
    }

    /**
     * 5. POST /api/astrologer/services/{id}/toggle-active
     * Quick activate/deactivate toggle.
     */
    public function toggleActive(Request $request, $id)
    {
        $admin = $request->user();

        $service = ProfessionalService::where('admin_id', $admin->id)->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found in your catalogue.',
            ], 404);
        }

        $service->is_active = !$service->is_active;
        $service->save();

        return response()->json([
            'success' => true,
            'message' => $service->is_active ? 'Service activated.' : 'Service deactivated.',
            'data' => $service,
        ]);
    }

    /**
     * 6. DELETE /api/astrologer/services/{id}
     * Safe deletion preserving historical appointments and consultations.
     */
    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        $service = ProfessionalService::where('admin_id', $admin->id)->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found in your catalogue.',
            ], 404);
        }

        $hasUsage = $service->hasHistoricalUsage();

        // Always soft delete to preserve snapshot references
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => $hasUsage
                ? 'Service safely archived to protect historical appointments and consultation records.'
                : 'Service removed from your catalogue.',
        ]);
    }

    /**
     * 7. GET /api/astrologer/services/templates
     * Return suggested service templates across all professional categories.
     */
    public function templates(Request $request)
    {
        $templates = [
            'astrologer' => [
                [
                    'name' => 'Kundli Analysis & Life Predictions',
                    'category' => 'astrologer',
                    'specialization' => 'Vedic Astrology',
                    'pricing_model' => 'fixed',
                    'price' => 1100,
                    'currency' => 'INR',
                    'duration_minutes' => 45,
                    'delivery_methods' => ['online', 'phone_call', 'in_person'],
                    'description' => 'Comprehensive analysis of Janam Kundli, planetary yogas, current dasha timeline, and major life guidance.',
                    'preparation_instructions' => 'Please provide accurate birth date, exact birth time, and birth place.',
                ],
                [
                    'name' => 'Birth Chart Consultation (Detailed)',
                    'category' => 'astrologer',
                    'specialization' => 'Vedic Astrology',
                    'pricing_model' => 'fixed',
                    'price' => 1500,
                    'currency' => 'INR',
                    'duration_minutes' => 60,
                    'delivery_methods' => ['online', 'phone_call', 'in_person'],
                    'description' => 'In-depth review of D-1, D-9 Navamsha, Vimshottari dasha, and Parashari aspects with remedial advice.',
                    'preparation_instructions' => 'Bring previous birth charts or exact birth details.',
                ],
                [
                    'name' => 'Marriage & Kundli Matching (Kundli Milan)',
                    'category' => 'astrologer',
                    'specialization' => 'Matchmaking',
                    'pricing_model' => 'fixed',
                    'price' => 1100,
                    'currency' => 'INR',
                    'duration_minutes' => 45,
                    'delivery_methods' => ['online', 'phone_call', 'in_person'],
                    'description' => 'Detailed Guna Milan, Manglik Dosha evaluation, Bhanga cancellations, and longevity/compatibility analysis.',
                    'preparation_instructions' => 'Provide birth details of both the bride and groom.',
                ],
                [
                    'name' => 'Career & Financial Astrology Consultation',
                    'category' => 'astrologer',
                    'specialization' => 'Predictive Astrology',
                    'pricing_model' => 'fixed',
                    'price' => 800,
                    'currency' => 'INR',
                    'duration_minutes' => 30,
                    'delivery_methods' => ['online', 'phone_call'],
                    'description' => 'Focussed consultation on 10th house, professional transitions, business ventures, and financial stability.',
                ],
                [
                    'name' => 'Shubh Muhurat Consultation',
                    'category' => 'astrologer',
                    'specialization' => 'Muhurat',
                    'pricing_model' => 'fixed',
                    'price' => 500,
                    'currency' => 'INR',
                    'duration_minutes' => 30,
                    'delivery_methods' => ['online', 'phone_call'],
                    'description' => 'Auspicious timing calculation for weddings, property purchase, Griha Pravesh, naming ceremonies, or business launch.',
                ],
                [
                    'name' => 'Prashna (Horary) Astrology Consultation',
                    'category' => 'astrologer',
                    'specialization' => 'Prashna Kundli',
                    'pricing_model' => 'fixed',
                    'price' => 700,
                    'currency' => 'INR',
                    'duration_minutes' => 30,
                    'delivery_methods' => ['online', 'phone_call'],
                    'description' => 'Time-chart (Prashna Lagna) analysis for urgent specific questions when birth time is uncertain.',
                ],
            ],
            'pandit_purohit' => [
                [
                    'name' => 'Griha Pravesh & Vastu Shanti Puja',
                    'category' => 'pandit_purohit',
                    'specialization' => 'Griha Pravesh & Vastu Shanti',
                    'pricing_model' => 'starting_from',
                    'price' => 5100,
                    'currency' => 'INR',
                    'duration_minutes' => 180,
                    'delivery_methods' => ['home_visit', 'at_location'],
                    'description' => 'Complete traditional Griha Pravesh vidhi, Gauri-Ganesh sthapana, Navgraha pujan, and Vastu Shanti hawan.',
                    'preparation_instructions' => 'Puja samagri list will be provided in advance. Ensure clean premises and copper kalash.',
                ],
                [
                    'name' => 'Shri Satyanarayan Katha & Puja',
                    'category' => 'pandit_purohit',
                    'specialization' => 'Satyanarayan Katha',
                    'pricing_model' => 'fixed',
                    'price' => 3100,
                    'currency' => 'INR',
                    'duration_minutes' => 120,
                    'delivery_methods' => ['home_visit', 'in_person'],
                    'description' => 'Sacred reading of 5 adhyayas of Satyanarayan Katha with Panchamrit abhishek, aarti and prasad distribution.',
                ],
                [
                    'name' => 'Rudrabhishek & Mahamrityunjaya Hawan',
                    'category' => 'pandit_purohit',
                    'specialization' => 'Rudrabhishek & Mahamrityunjaya',
                    'pricing_model' => 'fixed',
                    'price' => 3500,
                    'currency' => 'INR',
                    'duration_minutes' => 120,
                    'delivery_methods' => ['home_visit', 'at_location'],
                    'description' => 'Shukla Yajurvedic Rudra paath with continuous milk, gangajal, honey abhishek and Mahamrityunjaya ahutis.',
                ],
                [
                    'name' => 'Ganesh & Lakshmi Puja',
                    'category' => 'pandit_purohit',
                    'specialization' => 'Pooja',
                    'pricing_model' => 'fixed',
                    'price' => 2100,
                    'currency' => 'INR',
                    'duration_minutes' => 90,
                    'delivery_methods' => ['home_visit', 'in_person'],
                    'description' => 'Traditional Ganesh-Lakshmi archana for prosperity, shop opening, Diwali, or auspicious commencement.',
                ],
                [
                    'name' => 'Navgraha Shanti & Dosha Nivaran Hawan',
                    'category' => 'pandit_purohit',
                    'specialization' => 'Navgraha Shanti',
                    'pricing_model' => 'starting_from',
                    'price' => 4100,
                    'currency' => 'INR',
                    'duration_minutes' => 150,
                    'delivery_methods' => ['home_visit'],
                    'description' => 'Specific Samidha ahutis for 9 planets to alleviate dasha afflictions, Rahu-Ketu, or Sade Sati distress.',
                ],
                [
                    'name' => 'Vivah Sanskar (Vedic Wedding Rituals)',
                    'category' => 'pandit_purohit',
                    'specialization' => 'Vivah Sanskar (Weddings)',
                    'pricing_model' => 'custom',
                    'price' => null,
                    'currency' => 'INR',
                    'duration_minutes' => 240,
                    'delivery_methods' => ['home_visit', 'at_location'],
                    'description' => 'Complete traditional Vedic marriage ceremony from Kanyadaan, Saptapadi, Mangalashtaka to Sindoor Daan.',
                ],
            ],
            'vastu_consultant' => [
                [
                    'name' => 'Residential Vastu Consultation & Site Visit',
                    'category' => 'vastu_consultant',
                    'specialization' => 'Residential Vastu',
                    'pricing_model' => 'starting_from',
                    'price' => 7500,
                    'currency' => 'INR',
                    'duration_minutes' => 120,
                    'delivery_methods' => ['home_visit', 'at_location'],
                    'description' => 'On-site energy assessment, direction mapping with magnetic compass/Lecher antenna, and layout audit for rooms, kitchen, and temple.',
                    'preparation_instructions' => 'Please keep architectural floor plan and property deed layout ready.',
                ],
                [
                    'name' => 'Commercial & Industrial Vastu Audit',
                    'category' => 'vastu_consultant',
                    'specialization' => 'Commercial / Office Vastu',
                    'pricing_model' => 'starting_from',
                    'price' => 15000,
                    'currency' => 'INR',
                    'duration_minutes' => 180,
                    'delivery_methods' => ['home_visit', 'at_location'],
                    'description' => 'Vastu alignment for factories, corporate offices, clinics, retail shops, and warehouses to boost productivity and cash flow.',
                ],
                [
                    'name' => 'Architectural Floor Plan Analysis (Online)',
                    'category' => 'vastu_consultant',
                    'specialization' => 'Residential Vastu',
                    'pricing_model' => 'fixed',
                    'price' => 3500,
                    'currency' => 'INR',
                    'duration_minutes' => 60,
                    'delivery_methods' => ['online'],
                    'description' => 'Remote 16-zone Shakti Chakra grid evaluation of apartment floor plans prior to purchase or interior remodeling.',
                ],
                [
                    'name' => 'Non-Destructive Vastu Remedial Plan',
                    'category' => 'vastu_consultant',
                    'specialization' => 'Vastu Remedial without Demolition',
                    'pricing_model' => 'fixed',
                    'price' => 5000,
                    'currency' => 'INR',
                    'duration_minutes' => 90,
                    'delivery_methods' => ['home_visit', 'online'],
                    'description' => 'Application of elemental remedies, metal strips, color therapy, pyramids, and mirrors to neutralize Vastu doshas without structural demolition.',
                ],
            ],
            'numerologist' => [
                [
                    'name' => 'Personal Name Numerology & Correction',
                    'category' => 'numerologist',
                    'specialization' => 'Name Number Correction',
                    'pricing_model' => 'fixed',
                    'price' => 1500,
                    'currency' => 'INR',
                    'duration_minutes' => 45,
                    'delivery_methods' => ['online', 'phone_call'],
                    'description' => 'Chaldean & Pythagorean analysis of Mulank and Bhagyank with auspicious spelling adjustments and signature guidance.',
                ],
                [
                    'name' => 'Business & Brand Name Numerology',
                    'category' => 'numerologist',
                    'specialization' => 'Business & Brand Numerology',
                    'pricing_model' => 'fixed',
                    'price' => 3500,
                    'currency' => 'INR',
                    'duration_minutes' => 60,
                    'delivery_methods' => ['online', 'phone_call'],
                    'description' => 'Alignment of company name, product brand, logo color energy, and incorporation date with founder birth vibrations.',
                ],
                [
                    'name' => 'Mobile & Vehicle Number Analysis',
                    'category' => 'numerologist',
                    'specialization' => 'Mobile / Vehicle Number Analysis',
                    'pricing_model' => 'fixed',
                    'price' => 700,
                    'currency' => 'INR',
                    'duration_minutes' => 30,
                    'delivery_methods' => ['online', 'phone_call'],
                    'description' => 'Audit of contact number, license plate, and banking PIN codes for harmonic resonance with destiny numbers.',
                ],
            ],
            'palmist' => [
                [
                    'name' => 'Classical Palm Reading (Hastrekha)',
                    'category' => 'palmist',
                    'specialization' => 'Samudrika Shastra',
                    'pricing_model' => 'fixed',
                    'price' => 800,
                    'currency' => 'INR',
                    'duration_minutes' => 30,
                    'delivery_methods' => ['in_person', 'online'],
                    'description' => 'Analysis of heart, head, life, fate, and sun lines, palm texture, and planetary mounts according to Samudrika Shastra.',
                    'preparation_instructions' => 'If online, provide high-resolution photos of both palms in bright daylight.',
                ],
                [
                    'name' => 'Detailed Palmistry & Life Timeline Reading',
                    'category' => 'palmist',
                    'specialization' => 'Cheiro Palmistry',
                    'pricing_model' => 'fixed',
                    'price' => 1500,
                    'currency' => 'INR',
                    'duration_minutes' => 60,
                    'delivery_methods' => ['in_person', 'online'],
                    'description' => 'Deep analysis of minor signs (tridents, fish symbols, stars, islands), finger dermatoglyphics, and 7-year age timelines.',
                ],
            ],
            'spiritual_consultant' => [
                [
                    'name' => 'Personal Spiritual Guidance & Counseling',
                    'category' => 'spiritual_consultant',
                    'specialization' => 'Spiritual Consultation',
                    'pricing_model' => 'fixed',
                    'price' => 1200,
                    'currency' => 'INR',
                    'duration_minutes' => 45,
                    'delivery_methods' => ['online', 'in_person', 'phone_call'],
                    'description' => 'Compassionate spiritual guidance for grief, life purpose, spiritual awakening, and inner peace.',
                ],
                [
                    'name' => 'Mantra & Sadhana Guidance Session',
                    'category' => 'spiritual_consultant',
                    'specialization' => 'Mantra & Yantra Sadhana',
                    'pricing_model' => 'fixed',
                    'price' => 1500,
                    'currency' => 'INR',
                    'duration_minutes' => 60,
                    'delivery_methods' => ['online', 'in_person'],
                    'description' => 'Prescription of Ishta Devata mantra, correct pronunciation (Beej mantras), mala selection, and japa vidhi.',
                ],
                [
                    'name' => 'Chakra & Energy Consultation',
                    'category' => 'spiritual_consultant',
                    'specialization' => 'Chakra & Energy Balancing',
                    'pricing_model' => 'hourly',
                    'price' => 1500,
                    'currency' => 'INR',
                    'duration_minutes' => 60,
                    'delivery_methods' => ['online', 'in_person'],
                    'description' => 'Subtle energy diagnosis across 7 primary chakras with breathing pranayama, color therapy, and gemstone recommendations.',
                ],
            ],
            'other' => [
                [
                    'name' => 'Tarot Card Reading',
                    'category' => 'other',
                    'specialization' => 'Tarot & Oracle Reading',
                    'pricing_model' => 'fixed',
                    'price' => 999,
                    'currency' => 'INR',
                    'duration_minutes' => 30,
                    'delivery_methods' => ['online', 'in_person', 'phone_call'],
                    'description' => 'Celtic cross spread and three-card spread for immediate clarity on relationships, career choices, and upcoming obstacles.',
                ],
            ],
        ];

        // If category is specified, return just that category's templates
        if ($category = $request->query('category')) {
            return response()->json([
                'success' => true,
                'data' => $templates[$category] ?? [],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * 8. GET /api/astrologer/services/summary
     * Dashboard statistics on practitioner's active services and categories.
     */
    public function summary(Request $request)
    {
        $admin = $request->user();

        $services = ProfessionalService::where('admin_id', $admin->id)->get();

        $total = $services->count();
        $active = $services->where('is_active', true)->count();
        $inactive = $total - $active;

        $categoryCounts = $services->groupBy('category')->map->count();
        $pricingModelCounts = $services->groupBy('pricing_model')->map->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_services' => $total,
                'active_services' => $active,
                'inactive_services' => $inactive,
                'category_counts' => $categoryCounts,
                'pricing_model_counts' => $pricingModelCounts,
            ],
        ]);
    }
}
