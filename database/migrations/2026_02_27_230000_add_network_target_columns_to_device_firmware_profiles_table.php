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
            $table->string('http_base_url', 200)->nullable()->after('server_host');
            $table->string('mqtt_broker', 120)->nullable()->after('http_endpoint');
            $table->boolean('http_tls_insecure')->default(true)->after('mqtt_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_firmware_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'http_base_url',
                'mqtt_broker',
                'http_tls_insecure',
            ]);
        });
    }
};
