<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained('service_jobs')->onDelete('cascade');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_status_logs');
    }
};
