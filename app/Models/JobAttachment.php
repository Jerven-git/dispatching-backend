<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_job_id', 'uploaded_by', 'file_name', 'file_path', 'file_type', 'file_size', 'category'])]
class JobAttachment extends Model
{
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
