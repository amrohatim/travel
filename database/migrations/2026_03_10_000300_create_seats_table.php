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
        Schema::create('seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('traveler_id')->constrained('users');
            $table->foreignId('flight_id')->constrained('flights');
            $table->foreignId('booking_id')->constrained('bookings');
            $table->string('traveler_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
