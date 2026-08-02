<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_support_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('support_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['office_id', 'support_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_support_user');
    }
};
