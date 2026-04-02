<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'exists:customers,id'],
            'service_id' => ['sometimes', 'exists:services,id'],
            'status' => ['sometimes', 'in:pending,assigned,on_the_way,in_progress,completed,cancelled'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['sometimes', 'string', 'max:500'],
            'scheduled_date' => ['sometimes', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
