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
        Schema::table('transaksi_ayam_bulanan', function (Blueprint $table) {
            //
            $table->integer('lokasi_id')->nullable(false);
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksi_ayam_bulanan', function (Blueprint $table) {
            //
            $table->dropColumn('lokasi_id');
        });
    }
};
