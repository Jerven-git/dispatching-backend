<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Technicians can only update their own assigned jobs
        if ($this->user()->isTechnician()) {
            return $this->route('service_job')->technician_id === $this->user()->id;
        }

        return $this->user()->isAdmin() || $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,assigned,on_the_way,in_progress,completed,cancelled'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
