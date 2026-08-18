<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->json('selected_seat_numbers')->nullable()->after('image');
        });

        Schema::table('seats', function (Blueprint $table): void {
            $table->unsignedInteger('seat_number')->nullable()->after('traveler_name');
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table): void {
            $table->dropColumn('seat_number');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('selected_seat_numbers');
        });
    }
};
