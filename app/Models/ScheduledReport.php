<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'report_type',
    'frequency',
    'recipients',
    'parameters',
    'created_by',
    'is_active',
    'last_sent_at',
])]
class ScheduledReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'parameters' => 'array',
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDueToday(): bool
    {
        return match ($this->frequency) {
            'daily' => true,
            'weekly' => now()->isMonday(),
            'monthly' => now()->day === 1,
            default => false,
        };
    }
}
