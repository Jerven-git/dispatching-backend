<?php

namespace App\Events;

use App\Models\ServiceJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TechnicianAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ServiceJob $job,
    ) {}
}
