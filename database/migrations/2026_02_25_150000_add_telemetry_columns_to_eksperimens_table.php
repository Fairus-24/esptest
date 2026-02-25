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
            $table->unsignedBigInteger('packet_seq')->nullable()->after('daya_mw');
            $table->integer('rssi_dbm')->nullable()->after('packet_seq');
            $table->float('tx_duration_ms')->nullable()->after('rssi_dbm');
            $table->unsignedInteger('payload_bytes')->nullable()->after('tx_duration_ms');
            $table->unsignedBigInteger('uptime_s')->nullable()->after('payload_bytes');
            $table->unsignedInteger('free_heap_bytes')->nullable()->after('uptime_s');

            $table->index(['protokol', 'device_id', 'packet_seq'], 'eksperimens_protocol_device_seq_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eksperimens', function (Blueprint $table) {
            $table->dropIndex('eksperimens_protocol_device_seq_idx');
            $table->dropColumn([
                'packet_seq',
                'rssi_dbm',
                'tx_duration_ms',
                'payload_bytes',
                'uptime_s',
                'free_heap_bytes',
            ]);
        });
    }
};
