<?php

namespace App\Services;

use App\Models\ServiceJob;
use Illuminate\Support\Collection;

class RouteOptimizationService
{
    private const ROAD_FACTOR = 1.4; // straight-line to road distance multiplier

    private const AVERAGE_SPEED_KMH = 40; // urban average speed

    /**
     * Get an optimized route for a technician's jobs on a given date.
     *
     * Uses nearest-neighbor algorithm starting from the technician's current location
     * or the first job if no location is available.
     *
     * @return array{jobs: array, total_distance_km: float, estimated_travel_minutes: int}
     */
    public function optimizeRoute(
        int $technicianId,
        string $date,
        ?float $startLat = null,
        ?float $startLng = null,
    ): array {
        $jobs = ServiceJob::where('technician_id', $technicianId)
            ->whereDate('scheduled_date', $date)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with(['customer:id,name', 'service:id,name,estimated_duration_minutes'])
            ->get();

        if ($jobs->isEmpty()) {
            return [
                'jobs' => [],
                'total_distance_km' => 0,
                'estimated_travel_minutes' => 0,
            ];
        }

        // If no starting position, use first job's location
        if ($startLat === null || $startLng === null) {
            $firstJob = $jobs->first();
            $startLat = (float) $firstJob->latitude;
            $startLng = (float) $firstJob->longitude;
        }

        $ordered = $this->nearestNeighbor($jobs, $startLat, $startLng);

        $totalDistance = 0;
        $currentLat = $startLat;
        $currentLng = $startLng;

        $result = [];
        $order = 1;

        foreach ($ordered as $job) {
            $distance = LocationTrackingService::haversineDistance(
                $currentLat,
                $currentLng,
                (float) $job->latitude,
                (float) $job->longitude,
            );
            $roadDistance = $distance * self::ROAD_FACTOR;
            $travelMinutes = $roadDistance > 0
                ? (int) round(($roadDistance / self::AVERAGE_SPEED_KMH) * 60)
                : 0;

            $result[] = [
                'order' => $order++,
                'job_id' => $job->id,
                'reference_number' => $job->reference_number,
                'customer' => $job->customer?->name,
                'service' => $job->service?->name,
                'address' => $job->address,
                'latitude' => $job->latitude,
                'longitude' => $job->longitude,
                'scheduled_time' => $job->scheduled_time,
                'estimated_duration_minutes' => $job->service?->estimated_duration_minutes,
                'distance_from_previous_km' => round($roadDistance, 2),
                'travel_minutes_from_previous' => $travelMinutes,
                'status' => $job->status,
            ];

            $totalDistance += $roadDistance;
            $currentLat = (float) $job->latitude;
            $currentLng = (float) $job->longitude;
        }

        return [
            'jobs' => $result,
            'total_distance_km' => round($totalDistance, 2),
            'estimated_travel_minutes' => (int) round(($totalDistance / self::AVERAGE_SPEED_KMH) * 60),
        ];
    }

    /**
     * Nearest-neighbor algorithm: at each step, visit the closest unvisited job.
     */
    private function nearestNeighbor(Collection $jobs, float $startLat, float $startLng): array
    {
        $unvisited = $jobs->all();
        $ordered = [];
        $currentLat = $startLat;
        $currentLng = $startLng;

        while (count($unvisited) > 0) {
            $nearestIdx = null;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($unvisited as $idx => $job) {
                $dist = LocationTrackingService::haversineDistance(
                    $currentLat,
                    $currentLng,
                    (float) $job->latitude,
                    (float) $job->longitude,
                );

                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            $nearest = $unvisited[$nearestIdx];
            $ordered[] = $nearest;
            $currentLat = (float) $nearest->latitude;
            $currentLng = (float) $nearest->longitude;
            unset($unvisited[$nearestIdx]);
        }

        return $ordered;
    }
}
