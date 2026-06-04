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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_company_id')
                ->nullable()
                ->after('role')
                ->constrained('parent_companies')
                ->nullOnDelete();
        });

        $officeIds = DB::table('users')
            ->where('role', 'office')
            ->whereNull('parent_company_id')
            ->pluck('id');

        if ($officeIds->isNotEmpty()) {
            $fallbackCompanyId = DB::table('parent_companies')->insertGetId([
                'name' => 'Unassigned Parent Company',
                'image' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')
                ->whereIn('id', $officeIds)
                ->update(['parent_company_id' => $fallbackCompanyId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_company_id');
        });
    }
};
