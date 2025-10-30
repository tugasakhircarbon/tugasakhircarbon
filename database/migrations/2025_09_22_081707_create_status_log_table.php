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
        Schema::create('status_log', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->index();
            $table->timestamp('timestamp')->index();
            $table->text('status');
            $table->string('ip_address', 45)->nullable();
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
        Schema::dropIfExists('status_log');
    }
};
