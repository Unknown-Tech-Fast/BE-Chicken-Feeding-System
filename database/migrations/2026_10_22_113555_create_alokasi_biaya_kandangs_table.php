<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alokasi_biaya_kandang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_ayam_id')
                ->unique()
                ->constrained('transaksi_ayam_bulanan')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('nbo_ayam', 20, 2)->default(0);
            $table->decimal('nbo_ayam_dp', 20, 2)->default(0);
            $table->decimal('alokasi_biaya', 20, 2)->default(0);
            $table->decimal('koreksi_alokasi', 20, 2)->default(0);
            $table->decimal('mati_rupiah', 20, 2)->default(0);
            $table->decimal('jual_rupiah', 20, 2)->default(0);
            $table->decimal('mutasi_koreksi_rupiah', 20, 2)->default(0);
            $table->decimal('nba_ayam', 20, 2)->default(0);
            $table->unsignedSmallInteger('umur_deplesi')->default(9);
            $table->decimal('deplesi_rupiah', 20, 2)->default(0);
            $table->decimal('nba_ayam_dp', 20, 2)->default(0);
            $table->decimal('biaya_growing', 20, 2)->default(0);
            $table->decimal('mutasi_rupiah', 20, 2)->default(0);
            $table->string('status_perhitungan', 20)->default('DRAFT'); // DRAFT, FINAL
            $table->timestamps();

            $table->index('status_perhitungan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_biaya_kandang');
    }
};
