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
        Schema::table('device_firmware_profiles', function (Blueprint $table) {
            $table->unsignedInteger('http_read_timeout_ms')->default(5000)->after('http_tls_insecure');
            $table->unsignedInteger('sensor_interval_ms')->default(5000)->after('dht_model');
            $table->unsignedInteger('http_interval_ms')->default(10000)->after('sensor_interval_ms');
            $table->unsignedInteger('mqtt_interval_ms')->default(10000)->after('http_interval_ms');
            $table->unsignedInteger('dht_min_read_interval_ms')->default(1500)->after('mqtt_interval_ms');
            $table->unsignedTinyInteger('core_debug_level')->default(0)->after('dht_min_read_interval_ms');
            $table->unsignedInteger('mqtt_max_packet_size')->default(2048)->after('core_debug_level');
            $table->unsignedInteger('monitor_speed')->default(115200)->after('mqtt_max_packet_size');
            $table->string('monitor_port', 80)->nullable()->after('monitor_speed');
            $table->string('upload_port', 80)->nullable()->after('monitor_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_firmware_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'http_read_timeout_ms',
                'sensor_interval_ms',
                'http_interval_ms',
                'mqtt_interval_ms',
                'dht_min_read_interval_ms',
                'core_debug_level',
                'mqtt_max_packet_size',
                'monitor_speed',
                'monitor_port',
                'upload_port',
            ]);
        });
    }
};

