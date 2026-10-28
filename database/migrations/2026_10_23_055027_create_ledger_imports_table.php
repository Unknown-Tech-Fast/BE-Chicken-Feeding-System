<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->char('file_sha256', 64)->unique();
            $table->string('status', 32)->default('queued')->index();
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->unsignedInteger('total_chunks')->default(0);
            $table->unsignedInteger('processed_chunks')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_imports');
    }
};
