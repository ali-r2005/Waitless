<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_user', function (Blueprint $table) {
            $table->foreignId('staff_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('queue_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
    }
};
