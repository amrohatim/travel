<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('flights', function (Blueprint $table) {
        $table->string('office_name')->after('office_id'); // بعد معرف المكتب
    });
}

public function down()
{
    Schema::table('flights', function (Blueprint $table) {
        $table->dropColumn('office_name');
    });
}

};
