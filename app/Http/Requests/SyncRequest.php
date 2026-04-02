<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'last_synced_at' => ['required', 'date'],
            'actions' => ['required', 'array', 'max:100'],
            'actions.*.type' => ['required', 'string', 'in:status_update,location_update,checklist_toggle,comment'],
            'actions.*.timestamp' => ['required', 'date'],

            // status_update
            'actions.*.job_id' => ['required_unless:actions.*.type,location_update', 'nullable', 'integer', 'exists:service_jobs,id'],
            'actions.*.status' => ['required_if:actions.*.type,status_update', 'nullable', 'string'],
            'actions.*.technician_notes' => ['nullable', 'string'],

            // location_update
            'actions.*.latitude' => ['required_if:actions.*.type,location_update', 'nullable', 'numeric', 'between:-90,90'],
            'actions.*.longitude' => ['required_if:actions.*.type,location_update', 'nullable', 'numeric', 'between:-180,180'],
            'actions.*.accuracy' => ['nullable', 'numeric', 'min:0'],

            // checklist_toggle
            'actions.*.checklist_item_id' => ['required_if:actions.*.type,checklist_toggle', 'nullable', 'integer'],

            // comment
            'actions.*.body' => ['required_if:actions.*.type,comment', 'nullable', 'string', 'max:2000'],
        ];
    }
}
