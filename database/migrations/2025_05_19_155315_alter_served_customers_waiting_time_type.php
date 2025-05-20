<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('served_customers', function (Blueprint $table) {
            $table->float('waiting_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('served_customers', function (Blueprint $table) {
            $table->integer('waiting_time')->nullable()->change();
        });
    }
};