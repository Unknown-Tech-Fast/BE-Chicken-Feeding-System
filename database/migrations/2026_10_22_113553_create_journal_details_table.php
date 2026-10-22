<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('journal_detail', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('import_batch_id')
                ->nullable()
                ->constrained('import_batches')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('sumber', 30)->default('IMPORT_ODOO'); // IMPORT_ODOO, ENGINE_ALOKASI
            $table->string('sheet_sumber', 30)->nullable(); // OVK, PAKAN.VOD, AYAM
            $table->unsignedSmallInteger('tahun');
            $table->unsignedSmallInteger('bulan');
            $table->string('field1', 40)->nullable(); // PEMBELIAN / PEMAKAIAN
            $table->string('divisi', 80)->nullable();

            $table->foreignId('lokasi_id')
                ->nullable()
                ->constrained('master_lokasi')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('lokasi_raw', 200)->nullable();

            $table->foreignId('kandang_id')
                ->nullable()
                ->constrained('master_kandang')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('status', 30)->nullable();
            $table->string('tipe_internal', 80)->nullable();
            $table->string('rekening_jenis', 100)->nullable();

            $table->string('kode_coa', 50);
            $table->string('nama_coa', 200);
            $table->string('rekanan', 200)->nullable();
            $table->text('label')->nullable();
            $table->string('referensi', 150)->nullable();
            $table->string('nomor', 150)->nullable();
            $table->date('tanggal');

            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('kredit', 20, 2)->default(0);
            $table->decimal('mutasi', 20, 2)->default(0);
            $table->timestamps();

            $table->foreign('kode_coa', 'journal_detail_kode_coa_fk')
                ->references('kode_coa')
                ->on('master_coa_mapping')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->index(['tahun', 'bulan', 'lokasi_raw', 'kode_coa'], 'journal_period_location_coa_idx');
            $table->index(['sheet_sumber', 'tahun', 'bulan'], 'journal_sheet_period_idx');
            $table->index(['import_batch_id'], 'journal_import_batch_idx');
            $table->index(['tanggal', 'nomor'], 'journal_date_number_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_detail');
    }
};
