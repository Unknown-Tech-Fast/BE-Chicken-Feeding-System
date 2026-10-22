<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_kandang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lokasi_id')
                ->constrained('master_lokasi')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode_kandang', 80);
            $table->unsignedInteger('kapasitas')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lokasi_id', 'kode_kandang']);
            $table->index(['lokasi_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kandang');
    }
};
