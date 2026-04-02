<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('technician_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', [
                'pending',
                'assigned',
                'on_the_way',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->text('description')->nullable();
            $table->text('address');
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('technician_notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('recurring_frequency', ['none', 'daily', 'weekly', 'biweekly', 'monthly'])->default('none');
            $table->date('recurring_end_date')->nullable();
            $table->unsignedBigInteger('parent_job_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_job_id')->references('id')->on('service_jobs')->nullOnDelete();
            $table->index('status');
            $table->index('scheduled_date');
            $table->index('recurring_frequency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
    }
};
