<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geo\GeocodingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function __construct(
        protected GeocodingServiceInterface $geocodingService
    ) {}

    /**
     * Search verified cities for astrological calculations.
     */
    public function cities(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $limit = min((int) $request->query('limit', 20), 50);

        $results = $this->geocodingService->searchCities($query, $limit > 0 ? $limit : 20);

        return response()->json([
            'success' => true,
            'message' => 'Cities retrieved successfully',
            'data'    => $results,
        ]);
    }
}
