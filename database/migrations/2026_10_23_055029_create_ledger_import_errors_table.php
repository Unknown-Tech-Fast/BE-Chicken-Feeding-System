<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('ledger_imports')->cascadeOnDelete();
            $table->string('source_sheet', 80)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('column_name', 120)->nullable();
            $table->text('raw_value')->nullable();
            $table->text('message');
            $table->timestamps();
            $table->index(['import_id', 'source_sheet', 'source_row'], 'ledger_import_error_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_import_errors');
    }
};
