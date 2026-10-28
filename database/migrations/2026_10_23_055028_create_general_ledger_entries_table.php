<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('general_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('ledger_imports')->cascadeOnDelete();
            $table->string('source_sheet', 80);
            $table->unsignedInteger('source_row');
            $table->unsignedSmallInteger('month_number')->nullable();
            $table->string('tipe_transaksi', 40)->nullable();
            $table->string('division')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->nullable();
            $table->string('internal_type')->nullable();
            $table->string('account_type')->nullable();
            $table->string('coa_code', 100)->nullable()->index();
            $table->string('coa_name')->nullable();
            $table->string('partner_name')->nullable()->index();
            $table->text('deskripsi_transaksi')->nullable();
            $table->text('reference')->nullable();
            $table->string('document_number', 150)->nullable()->index();
            $table->date('transaction_date')->nullable()->index();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->string('jenis_transaksi', 30)->index();
            $table->decimal('mutasi', 20, 2)->default(0);
            $table->jsonb('source_payload')->nullable();
            $table->char('row_hash', 64);
            $table->timestamps();

            $table->unique(['import_id', 'source_sheet', 'source_row'], 'ledger_import_sheet_row_unique');
            $table->index(['transaction_date', 'coa_code'], 'ledger_date_coa_idx');
            $table->index(['jenis_transaksi', 'transaction_date'], 'ledger_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledger_entries');
    }
};
