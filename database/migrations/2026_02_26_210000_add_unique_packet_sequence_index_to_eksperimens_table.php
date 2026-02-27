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
            $table->unique(
                ['device_id', 'protokol', 'packet_seq'],
                'eksperimens_device_protocol_packet_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eksperimens', function (Blueprint $table) {
            $table->dropUnique('eksperimens_device_protocol_packet_unique');
        });
    }
};
