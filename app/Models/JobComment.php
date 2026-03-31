<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['service_job_id', 'user_id', 'body', 'is_internal'])]
class JobComment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
