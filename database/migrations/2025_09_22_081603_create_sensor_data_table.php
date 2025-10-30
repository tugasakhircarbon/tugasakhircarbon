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
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->timestamp('timestamp')->index();
            $table->float('humidity')->nullable();
            $table->float('temperature_c')->nullable();
            $table->float('temperature_f')->nullable();
            $table->float('co_ppm')->nullable();
            $table->float('nh3_ppm')->nullable();
            $table->float('no2_ppm')->nullable();
            $table->float('hydrocarbon_ppm')->nullable();
            // Arduino tidak mengukur CO2, CH4, N2O langsung - hanya CO, NH3, NO2
            // $table->float('co2_ppm')->nullable();
            // $table->float('ch4_ppm')->nullable(); // Tidak digunakan lagi
            // $table->float('n2o_ppm')->nullable(); // Tidak digunakan lagi
            $table->float('pm_density')->nullable();
            $table->timestamps();
            
            // Indexes untuk performa query
            $table->index(['device_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_data');
    }
};
