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
            'cancelled_at' => $this->cancelled_at,
            'technician_notes' => $this->technician_notes,
            'total_cost' => $this->total_cost,
            'recurring_frequency' => $this->recurring_frequency,
            'recurring_end_date' => $this->recurring_end_date?->format('Y-m-d'),
            'parent_job_id' => $this->parent_job_id,
            'signature_path' => $this->signature_path,
            'signed_by_name' => $this->signed_by_name,
            'signed_at' => $this->signed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'status_logs' => JobStatusLogResource::collection($this->whenLoaded('statusLogs')),
        ];
    }
}
