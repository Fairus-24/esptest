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
        Schema::create('eksperimens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained();
            $table->enum('protokol', ['MQTT', 'HTTP']);
            $table->float('suhu');
            $table->timestamp('timestamp_esp')->nullable();
            $table->timestamp('timestamp_server')->useCurrent();
            $table->float('latency_ms');
            $table->float('daya_mw');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eksperimens');
    }
};
