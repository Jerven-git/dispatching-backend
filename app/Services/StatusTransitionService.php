<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class StatusTransitionService
{
    /**
     * Allowed transitions: current status => [allowed next statuses].
     */
    private const TRANSITIONS = [
        'pending' => ['assigned', 'cancelled'],
        'assigned' => ['on_the_way', 'in_progress', 'pending', 'cancelled'],
        'on_the_way' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Validate that a status transition is allowed.
     *
     * @throws ValidationException
     */
    public function validate(string $currentStatus, string $newStatus): void
    {
        if ($currentStatus === $newStatus) {
            return;
        }

        $allowed = self::TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from '{$currentStatus}' to '{$newStatus}'."],
            ]);
        }
    }

    /**
     * Get allowed next statuses for a given status.
     */
    public function allowedTransitions(string $currentStatus): array
    {
        return self::TRANSITIONS[$currentStatus] ?? [];
    }
}
