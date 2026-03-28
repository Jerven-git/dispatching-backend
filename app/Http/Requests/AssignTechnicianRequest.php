<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isDispatcher();
    }

    public function rules(): array
    {
        return [
            'technician_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'technician')->where('is_active', true);
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.exists' => 'The selected user must be an active technician.',
        ];
    }
}
