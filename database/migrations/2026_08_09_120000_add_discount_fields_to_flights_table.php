<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->boolean('has_discount')->default(false)->after('office_name');
            $table->integer('discount_percentage')->nullable()->after('has_discount');
            $table->integer('discount_value')->nullable()->after('discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('flights', function (Blueprint $table): void {
            $table->dropColumn([
                'has_discount',
                'discount_percentage',
                'discount_value',
            ]);
        });
    }
};
