<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaksi_ayam_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_id')
                ->constrained('periode_closing')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('kandang_id')
                ->constrained('master_kandang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('master_ayam_id')
                ->constrained('master_ayam')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('flock_no', 100)->default('');
            $table->unsignedSmallInteger('umur_minggu')->default(0);
            $table->unsignedInteger('so_ekor')->default(0);
            $table->unsignedInteger('in_ekor')->default(0);
            $table->unsignedInteger('out_ekor')->default(0);
            $table->unsignedInteger('sa_ekor')->default(0);
            $table->unsignedInteger('mati_ekor')->default(0);
            $table->unsignedInteger('jual_ekor')->default(0);
            $table->integer('koreksi_ekor')->default(0);
            $table->timestamps();

            $table->unique(
                ['periode_id', 'kandang_id', 'master_ayam_id', 'flock_no'],
                'transaksi_ayam_period_kandang_phase_flock_uq'
            );
            $table->index(['kandang_id', 'periode_id'], 'transaksi_ayam_kandang_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_ayam_bulanan');
    }
};
