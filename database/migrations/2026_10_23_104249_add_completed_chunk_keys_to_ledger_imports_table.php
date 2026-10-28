
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_imports', function (Blueprint $table) {
            $table->jsonb('completed_chunk_keys')
                ->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_imports', function (Blueprint $table) {
            $table->dropColumn('completed_chunk_keys');
        });
    }
};
