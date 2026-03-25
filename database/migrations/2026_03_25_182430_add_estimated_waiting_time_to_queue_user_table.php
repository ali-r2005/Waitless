<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_user', function (Blueprint $table) {
            // Stored in seconds; null = not yet calculated, -1 = queue paused (unknown)
            $table->integer('estimated_waiting_time')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('queue_user', function (Blueprint $table) {
            $table->dropColumn('estimated_waiting_time');
        });
    }
};
