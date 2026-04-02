<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Job file attachments (photos, documents)
        Schema::create('job_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // image/jpeg, application/pdf, etc.
            $table->unsignedInteger('file_size'); // bytes
            $table->enum('category', ['before', 'after', 'document', 'other'])->default('other');
            $table->timestamps();

            $table->index(['service_job_id', 'category']);
        });

        // Checklist items per service type
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['service_id', 'sort_order']);
        });

        // Completed checklist entries per job
        Schema::create('job_checklist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['service_job_id', 'checklist_item_id']);
        });

        // Job comments thread
        Schema::create('job_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false); // internal = not visible to customer portal later
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_comments');
        Schema::dropIfExists('job_checklist_entries');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('job_attachments');
    }
};
