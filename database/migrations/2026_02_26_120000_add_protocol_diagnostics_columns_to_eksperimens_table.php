<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eksperimens', function (Blueprint $table) {
            $table->unsignedInteger('sensor_age_ms')->nullable()->after('free_heap_bytes');
            $table->unsignedBigInteger('sensor_read_seq')->nullable()->after('sensor_age_ms');
            $table->unsignedBigInteger('send_tick_ms')->nullable()->after('sensor_read_seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eksperimens', function (Blueprint $table) {
            $table->dropColumn([
                'sensor_age_ms',
                'sensor_read_seq',
                'send_tick_ms',
            ]);
        });
    }
};

