<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'address', 'city', 'state', 'zip_code', 'notes'])]
class Customer extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class);
    }
}
