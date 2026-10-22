<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_coa_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('kode_coa', 50)->unique();
            $table->string('nama_coa', 200);
            $table->string('kategori', 40); // PAKAN, DISINFECTANT, VACCINE, MEDICINE, OVERHEAD_OVK, PEMBELIAN_AYAM
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['kategori', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_coa_mapping');
    }
};
