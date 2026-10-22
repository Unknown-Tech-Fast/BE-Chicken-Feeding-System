<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_ayam', function (Blueprint $table) {
            $table->id();
            $table->string('kode_fase', 30)->unique(); // DOC, GROWING, LAYING, BROILER
            $table->string('nama_fase', 150);
            $table->unsignedSmallInteger('default_umur_deplesi')->default(0);
            $table->string('coa_persediaan', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_ayam');
    }
};
