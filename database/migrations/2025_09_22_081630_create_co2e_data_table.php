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
        Schema::create('co2e_data', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->timestamp('timestamp')->index();
            // $table->float('co2e_ppm'); // Tidak diperlukan lagi, menggunakan co2e_mg_m3
            
            // Arduino menggunakan CO, NH3, NO2 - tidak ada CO2, CH4, N2O langsung
            $table->float('co_contribution')->nullable()->comment('Kontribusi CO dalam CO2e (mg/m³)');
            $table->float('nh3_contribution')->nullable()->comment('Kontribusi NH3 dalam CO2e (mg/m³)');
            $table->float('no2_contribution')->nullable()->comment('Kontribusi NO2 dalam CO2e (mg/m³)');
            
            // GWP values yang digunakan untuk perhitungan (sesuai Arduino)
            $table->float('gwp_co')->nullable()->comment('GWP value untuk CO (2.0)');
            $table->float('gwp_nh3')->nullable()->comment('GWP value untuk NH3 (3.0)');
            $table->float('gwp_no2')->nullable()->comment('GWP value untuk NO2 (1.0)');
            
            // Mass concentrations (mg/m³) untuk debugging
            $table->float('co_mg_m3')->nullable()->comment('Mass concentration CO (mg/m³)');
            $table->float('nh3_mg_m3')->nullable()->comment('Mass concentration NH3 (mg/m³)');
            $table->float('no2_mg_m3')->nullable()->comment('Mass concentration NO2 (mg/m³)');
            
            // Total CO2e dalam mg/m³ (sesuai Arduino)
            $table->float('co2e_mg_m3')->nullable()->comment('Total CO2e dalam mg/m³');
            
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
        Schema::dropIfExists('co2e_data');
    }
};
