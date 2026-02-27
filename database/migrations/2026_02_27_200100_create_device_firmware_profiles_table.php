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
        Schema::create('device_firmware_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('board', 80)->default('esp32doit-devkit-v1');
            $table->string('wifi_ssid', 120)->default('Free');
            $table->string('wifi_password', 120)->default('gratiskok');
            $table->string('server_host', 120)->default('192.168.0.104');
            $table->string('http_endpoint', 160)->default('/esptest/public/api/http-data');
            $table->string('mqtt_host', 120)->default('192.168.0.104');
            $table->unsignedInteger('mqtt_port')->default(1883);
            $table->string('mqtt_topic', 120)->default('iot/esp32/suhu');
            $table->string('mqtt_user', 80)->default('esp32');
            $table->string('mqtt_password', 120)->default('esp32');
            $table->unsignedTinyInteger('dht_pin')->default(4);
            $table->string('dht_model', 20)->default('DHT11');
            $table->text('extra_build_flags')->nullable();
            $table->timestamps();

            $table->unique('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_firmware_profiles');
    }
};

