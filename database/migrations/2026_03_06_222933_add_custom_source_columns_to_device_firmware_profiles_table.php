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
            $table->longText('custom_main_cpp')->nullable()->after('extra_build_flags');
            $table->longText('custom_platformio_ini')->nullable()->after('custom_main_cpp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_firmware_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'custom_main_cpp',
                'custom_platformio_ini',
            ]);
        });
    }
};
