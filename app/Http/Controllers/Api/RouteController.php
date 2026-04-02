<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationTrackingService;
use App\Services\RouteOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function __construct(
        private RouteOptimizationService $routeService,
        private LocationTrackingService $locationService,
    ) {}

    /**
     * Get optimized route for a technician on a given date.
     * Used by admin/dispatcher: GET /technicians/{id}/route
     */
    public function show(Request $request, int $technicianId): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = $request->date ?? today()->toDateString();

        $location = $this->locationService->getLatestLocation($technicianId);

        $route = $this->routeService->optimizeRoute(
            $technicianId,
            $date,
            $location ? (float) $location->latitude : null,
            $location ? (float) $location->longitude : null,
        );

        return response()->json([
            'technician_id' => $technicianId,
            'date' => $date,
            'current_location' => $location ? [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'recorded_at' => $location->recorded_at,
            ] : null,
            'route' => $route,
        ]);
    }

    /**
     * Technician gets their own optimized route for today.
     * Used by technician: GET /my-route
     */
    public function myRoute(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $userId = $request->user()->id;
        $date = $request->date ?? today()->toDateString();

        $location = $this->locationService->getLatestLocation($userId);

        $route = $this->routeService->optimizeRoute(
            $userId,
            $date,
            $location ? (float) $location->latitude : null,
            $location ? (float) $location->longitude : null,
        );

        return response()->json([
            'date' => $date,
            'route' => $route,
        ]);
    }
}
