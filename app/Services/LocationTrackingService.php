<?php

namespace App\Services;

use App\Models\TechnicianLocation;
use App\Models\User;
use Illuminate\Support\Collection;

class LocationTrackingService
{
    public function recordLocation(int $userId, array $data): TechnicianLocation
    {
        return TechnicianLocation::create([
            'user_id' => $userId,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }

    public function getLatestLocation(int $userId): ?TechnicianLocation
    {
        return TechnicianLocation::where('user_id', $userId)
            ->orderByDesc('recorded_at')
            ->first();
    }

    public function getAllActiveLocations(): Collection
    {
        $technicians = User::where('role', 'technician')
            ->where('is_active', true)
            ->with('latestLocation')
            ->get();

        return $technicians->map(fn (User $tech) => [
            'id' => $tech->id,
            'name' => $tech->name,
            'phone' => $tech->phone,
            'location' => $tech->latestLocation ? [
                'latitude' => $tech->latestLocation->latitude,
                'longitude' => $tech->latestLocation->longitude,
                'accuracy' => $tech->latestLocation->accuracy,
                'heading' => $tech->latestLocation->heading,
                'speed' => $tech->latestLocation->speed,
                'recorded_at' => $tech->latestLocation->recorded_at,
            ] : null,
        ]);
    }

    /**
     * Calculate distance between two coordinates using the Haversine formula.
     *
     * @return float Distance in kilometers
     */
    public static function haversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Purge location records older than the given number of days.
     */
    public function purgeOldLocations(int $days = 30): int
    {
        return TechnicianLocation::where('recorded_at', '<', now()->subDays($days))->delete();
    }
}
