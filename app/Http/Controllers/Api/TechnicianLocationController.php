<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Services\LocationTrackingService;
use Illuminate\Http\JsonResponse;

class TechnicianLocationController extends Controller
{
    public function __construct(
        private LocationTrackingService $locationService,
    ) {}

    /**
     * Technician pushes their current location.
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->recordLocation(
            $request->user()->id,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Location recorded.',
            'location' => $location,
        ], 201);
    }

    /**
     * Admin/dispatcher views all active technician locations.
     */
    public function index(): JsonResponse
    {
        $locations = $this->locationService->getAllActiveLocations();

        return response()->json([
            'technicians' => $locations,
        ]);
    }

    /**
     * Admin/dispatcher views a specific technician's latest location.
     */
    public function show(int $technicianId): JsonResponse
    {
        $location = $this->locationService->getLatestLocation($technicianId);

        if (! $location) {
            return response()->json([
                'message' => 'No location data available for this technician.',
                'location' => null,
            ]);
        }

        return response()->json([
            'location' => $location,
        ]);
    }
}
