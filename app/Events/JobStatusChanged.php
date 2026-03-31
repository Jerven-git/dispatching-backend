<?php

namespace App\Events;

use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServiceJob $job,
        public string $oldStatus,
        public string $newStatus,
        public User $changedBy,
    ) {}
}
