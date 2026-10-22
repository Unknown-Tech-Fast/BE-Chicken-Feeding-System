<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('periode_closing', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedSmallInteger('bulan');
            $table->string('status', 20)->default('OPEN'); // OPEN, CALCULATED, LOCKED
            $table->decimal('total_biaya_operasional', 20, 2)->default(0);
            $table->timestampTz('locked_at')->nullable();
            $table->string('locked_by', 150)->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'bulan']);
            $table->index(['status', 'tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_closing');
    }
};
