<?php

namespace App\Http\Requests;

use App\Models\ServiceJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreServiceJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'technician_id' => ['nullable', 'exists:users,id'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['required', 'string', 'max:500'],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'total_cost' => ['nullable', 'numeric', 'min:0'],
            'recurring_frequency' => ['sometimes', 'in:none,daily,weekly,biweekly,monthly'],
            'recurring_end_date' => ['nullable', 'date', 'after:scheduled_date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->any() || $this->boolean('force')) {
                    return;
                }

                $technicianId = $this->input('technician_id');
                $date = $this->input('scheduled_date');

                if (! $technicianId || ! $date) {
                    return;
                }

                $conflict = ServiceJob::where('technician_id', $technicianId)
                    ->where('scheduled_date', $date)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->exists();

                if ($conflict) {
                    $validator->errors()->add(
                        'technician_id',
                        'This technician already has a job scheduled on this date. Submit again with force to override.'
                    );
                }
            },
        ];
    }
}
