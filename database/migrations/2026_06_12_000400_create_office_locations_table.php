<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_locations');
    }
};
