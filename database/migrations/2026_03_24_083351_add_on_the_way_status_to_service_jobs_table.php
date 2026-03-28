<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE service_jobs MODIFY COLUMN status ENUM('pending','assigned','on_the_way','in_progress','completed','cancelled') DEFAULT 'pending'");

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE service_jobs MODIFY COLUMN status ENUM('pending','assigned','in_progress','completed','cancelled') DEFAULT 'pending'");

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
