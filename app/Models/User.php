<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active', 'tenant_id', 'custom_role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function assignedJobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class, 'technician_id');
    }

    public function createdJobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class, 'created_by');
    }

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customRole(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class, 'custom_role_id');
    }

    public function hasPermission(string $slug): bool
    {
        // Admins have all permissions
        if ($this->isAdmin()) {
            return true;
        }

        // Check custom role permissions if assigned
        if ($this->custom_role_id && $this->customRole) {
            return $this->customRole->hasPermission($slug);
        }

        // Fall back to default role permissions
        return $this->getDefaultPermissions()->contains($slug);
    }

    private function getDefaultPermissions(): \Illuminate\Support\Collection
    {
        return match ($this->role) {
            'admin' => collect(['*']),
            'dispatcher' => collect([
                'jobs.view', 'jobs.create', 'jobs.update', 'jobs.assign',
                'customers.view', 'customers.create', 'customers.update',
                'invoices.view', 'invoices.create', 'invoices.update',
                'parts.view',
                'reports.view', 'reports.export',
                'analytics.view',
                'users.view',
                'services.view',
            ]),
            'technician' => collect([
                'jobs.view', 'jobs.update',
                'parts.view',
            ]),
            default => collect([]),
        };
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TechnicianLocation::class)->orderByDesc('recorded_at');
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(TechnicianLocation::class)->latestOfMany('recorded_at');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDispatcher(): bool
    {
        return $this->role === 'dispatcher';
    }

    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }
}
