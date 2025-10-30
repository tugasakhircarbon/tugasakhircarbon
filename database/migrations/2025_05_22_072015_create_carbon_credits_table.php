<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buat tabel baru dengan kolom tambahan
        Schema::create('carbon_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('nomor_kartu_keluarga');
            $table->enum('pemilik_kendaraan', ['milik sendiri', 'milik keluarga satu kk']);
            $table->string('nik_e_ktp');
            $table->string('nrkb');
            $table->string('nomor_rangka_5digit', 5);
            $table->string('vehicle_type')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('price_per_unit', 15, 2);
            $table->enum('status', [
                'pending',      // Menunggu persetujuan admin
                'approved',     // Disetujui admin, bisa request sale
                'pending_sale', // Mengajukan penjualan, menunggu approval
                'available',    // Tersedia di marketplace
                'rejected',     // Ditolak admin
                'sold'          // Sudah terjual
            ])->default('pending');

            // Kolom baru untuk pengajuan penjualan
            $table->decimal('sale_price_per_unit', 15, 2)->nullable();
            $table->decimal('quantity_to_sell', 10, 2)->nullable();
            $table->text('sale_notes')->nullable();
            $table->date('preferred_sale_date')->nullable();
            $table->timestamp('sale_requested_at')->nullable();
            $table->timestamp('sale_approved_at')->nullable();
            $table->timestamp('sale_rejected_at')->nullable();
            $table->text('sale_rejection_reason')->nullable();
            $table->unsignedBigInteger('sale_rejected_by')->nullable();
            
            $table->foreign('sale_rejected_by')->references('id')->on('users');
            
            // Kolom untuk tracking device MQTT
            $table->string('device_id')->nullable()->index();
            
            // Kolom untuk tracking emisi real-time
            $table->float('current_co2e_mg_m3')->nullable();
            $table->float('total_emissions_kg')->default(0);
            $table->float('daily_emissions_kg')->default(0);
            $table->float('monthly_emissions_kg')->default(0);
            
            // Kolom untuk tracking lokasi terakhir
            $table->double('last_latitude', 10, 8)->nullable();
            $table->double('last_longitude', 11, 8)->nullable();
            $table->float('last_speed_kmph')->nullable();
            
            // Kolom untuk tracking status sensor
            $table->timestamp('last_sensor_update')->nullable();
            $table->enum('sensor_status', ['active', 'inactive', 'error'])->default('inactive');
            
            // Kolom untuk automatic carbon credit adjustment
            $table->boolean('auto_adjustment_enabled')->default(true);
            $table->float('emission_threshold_kg')->default(100); // threshold untuk adjustment otomatis
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_credits');
    }
};
