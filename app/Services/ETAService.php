<?php

namespace App\Services;

use App\Models\ServiceJob;
use Carbon\Carbon;

class ETAService
{
    private const ROAD_FACTOR = 1.4;

    private const AVERAGE_SPEED_KMH = 40;

    /**
     * Calculate ETA for a service job based on the technician's current location.
     *
     * @return array{eta: string|null, distance_km: float|null, travel_minutes: int|null, technician_status: string, message: string}
     */
    public function calculateETA(ServiceJob $job): array
    {
        $job->loadMissing(['technician', 'service']);

        if (! $job->technician) {
            return [
                'eta' => null,
                'distance_km' => null,
                'travel_minutes' => null,
                'technician_status' => 'unassigned',
                'message' => 'No technician assigned to this job.',
            ];
        }

        if ($job->status === 'completed' || $job->status === 'cancelled') {
            return [
                'eta' => null,
                'distance_km' => null,
                'travel_minutes' => null,
                'technician_status' => $job->status,
                'message' => "Job is {$job->status}.",
            ];
        }

        if ($job->status === 'in_progress') {
            $remaining = $this->estimateRemainingMinutes($job);

            return [
                'eta' => null,
                'distance_km' => null,
                'travel_minutes' => null,
                'technician_status' => 'on_site',
                'message' => 'Technician is on site.',
                'estimated_completion_minutes' => $remaining,
            ];
        }

        // For assigned / on_the_way — calculate travel ETA
        if (! $job->latitude || ! $job->longitude) {
            return [
                'eta' => null,
                'distance_km' => null,
                'travel_minutes' => null,
                'technician_status' => $job->status,
                'message' => 'Job location coordinates not available.',
            ];
        }

        $location = (new LocationTrackingService)->getLatestLocation($job->technician->id);

        if (! $location) {
            return [
                'eta' => null,
                'distance_km' => null,
                'travel_minutes' => null,
                'technician_status' => $job->status,
                'message' => 'Technician location not available.',
            ];
        }

        $distance = LocationTrackingService::haversineDistance(
            (float) $location->latitude,
            (float) $location->longitude,
            (float) $job->latitude,
            (float) $job->longitude,
        );

        $roadDistance = $distance * self::ROAD_FACTOR;
        $travelMinutes = (int) round(($roadDistance / self::AVERAGE_SPEED_KMH) * 60);
        $eta = Carbon::now()->addMinutes($travelMinutes);

        return [
            'eta' => $eta->toIso8601String(),
            'distance_km' => round($roadDistance, 2),
            'travel_minutes' => $travelMinutes,
            'technician_status' => $job->status,
            'message' => "Estimated arrival in {$travelMinutes} minutes.",
        ];
    }

    /**
     * Estimate remaining minutes for an in-progress job.
     */
    private function estimateRemainingMinutes(ServiceJob $job): ?int
    {
        if (! $job->started_at || ! $job->service?->estimated_duration_minutes) {
            return null;
        }

        $elapsed = $job->started_at->diffInMinutes(now());
        $remaining = $job->service->estimated_duration_minutes - $elapsed;

        return max(0, (int) $remaining);
    }
}
