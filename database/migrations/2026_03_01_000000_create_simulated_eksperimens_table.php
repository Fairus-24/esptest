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
        Schema::create('simulated_eksperimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices');
            $table->enum('protokol', ['MQTT', 'HTTP']);
            $table->float('suhu');
            $table->float('kelembapan')->nullable();
            $table->timestamp('timestamp_esp')->nullable();
            $table->timestamp('timestamp_server')->useCurrent();
            $table->float('latency_ms');
            $table->float('daya_mw');
            $table->unsignedBigInteger('packet_seq')->nullable();
            $table->integer('rssi_dbm')->nullable();
            $table->float('tx_duration_ms')->nullable();
            $table->unsignedInteger('payload_bytes')->nullable();
            $table->unsignedBigInteger('uptime_s')->nullable();
            $table->unsignedInteger('free_heap_bytes')->nullable();
            $table->unsignedInteger('sensor_age_ms')->nullable();
            $table->unsignedBigInteger('sensor_read_seq')->nullable();
            $table->unsignedBigInteger('send_tick_ms')->nullable();
            $table->timestamps();

            $table->index(['protokol', 'device_id', 'packet_seq'], 'sim_eksperimens_protocol_device_seq_idx');
            $table->unique(
                ['device_id', 'protokol', 'packet_seq'],
                'sim_eksperimens_device_protocol_packet_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulated_eksperimens');
    }
};
