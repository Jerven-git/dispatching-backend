<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_id',
    'service_id',
    'technician_id',
    'created_by',
    'status',
    'priority',
    'description',
    'address',
    'scheduled_date',
    'scheduled_time',
    'started_at',
    'completed_at',
    'cancelled_at',
    'technician_notes',
    'total_cost',
    'recurring_frequency',
    'recurring_end_date',
    'parent_job_id',
    'signature_path',
    'signed_by_name',
    'signed_at',
])]
class ServiceJob extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_cost' => 'decimal:2',
            'recurring_end_date' => 'date',
            'signed_at' => 'datetime',
        ];
    }

    public function parentJob(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_job_id');
    }

    public function childJobs(): HasMany
    {
        return $this->hasMany(self::class, 'parent_job_id');
    }

    public function isRecurring(): bool
    {
        return $this->recurring_frequency !== 'none' && $this->recurring_frequency !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (ServiceJob $job) {
            $year = now()->format('Y');
            $lastJob = static::whereYear('created_at', $year)
                ->orderByDesc('id')
                ->first();

            $sequence = $lastJob
                ? (int) substr($lastJob->reference_number, -5) + 1
                : 1;

            $job->reference_number = sprintf('JOB-%s-%05d', $year, $sequence);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(JobStatusLog::class)->orderByDesc('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JobAttachment::class);
    }

    public function checklistEntries(): HasMany
    {
        return $this->hasMany(JobChecklistEntry::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(JobComment::class)->orderBy('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
