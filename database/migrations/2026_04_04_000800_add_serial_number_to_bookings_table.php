<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('serial_number', 8)->nullable()->unique()->after('id');
        });

        DB::table('bookings')
            ->whereNull('serial_number')
            ->orderBy('id')
            ->select('id')
            ->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    do {
                        $serial = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
                    } while (DB::table('bookings')->where('serial_number', $serial)->exists());

                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update(['serial_number' => $serial]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['serial_number']);
            $table->dropColumn('serial_number');
        });
    }
};
