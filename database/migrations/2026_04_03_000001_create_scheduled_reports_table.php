<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('report_type', [
                'summary',
                'jobs_by_status',
                'jobs_by_date',
                'technician_performance',
                'customer_lifetime_value',
                'job_profitability',
            ]);
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->json('recipients'); // array of email addresses
            $table->json('parameters')->nullable(); // optional filters (from, to, etc.)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
