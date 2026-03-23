<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'technician' => new UserResource($this->whenLoaded('technician')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'status' => $this->status,
            'priority' => $this->priority,
            'description' => $this->description,
            'address' => $this->address,
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'scheduled_time' => $this->scheduled_time,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'technician_notes' => $this->technician_notes,
            'total_cost' => $this->total_cost,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
