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
        Schema::create('gps_data', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->timestamp('timestamp')->index();
            $table->double('latitude', 10, 8)->nullable();
            $table->double('longitude', 11, 8)->nullable();
            $table->float('speed_kmph')->nullable();
            $table->string('date_recorded', 20)->nullable();
            $table->string('time_recorded', 20)->nullable();
            $table->timestamps();
            
            // Indexes untuk performa query
            $table->index(['device_id', 'timestamp']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_data');
    }
};
