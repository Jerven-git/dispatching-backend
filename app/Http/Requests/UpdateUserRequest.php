<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['sometimes', 'string', Password::min(8)],
            'role' => ['sometimes', 'in:admin,dispatcher,technician'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
