<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'service_id', 'description', 'preferred_date', 'preferred_time', 'address', 'status', 'converted_job_id', 'admin_notes'])]
class ServiceRequest extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function convertedJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class, 'converted_job_id');
    }
}
