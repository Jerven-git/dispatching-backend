<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobStatusLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'changed_by' => new UserResource($this->whenLoaded('changedByUser')),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
        ];
    }
}
