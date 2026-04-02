<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->decimal('unit_price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->string('unit')->default('piece'); // piece, meter, liter, kg, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sku');
            $table->index('is_active');
        });

        Schema::create('job_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->onDelete('cascade');
            $table->foreignId('part_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // snapshot at time of use
            $table->decimal('total_price', 10, 2);
            $table->foreignId('added_by')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('service_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_parts');
        Schema::dropIfExists('parts');
    }
};
