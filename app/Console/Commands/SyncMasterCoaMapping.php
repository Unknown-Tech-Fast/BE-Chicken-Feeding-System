<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMasterCoaMapping extends Command
{
    protected $signature = 'coa:sync {--sheet=OVK}';

    protected $description = 'Sinkronisasi COA buku besar ke master COA';

    public function handle(): int
    {
        $sheet = $this->option('sheet');

        $allowedSheets = ['OVK', 'PAKAN.OVD', 'AYAM'];

        if (!in_array($sheet, $allowedSheets, true)) {
            $this->error('Sheet tidak valid.');

            return self::FAILURE;
        }

        $now = now();

        $rows = DB::table('general_ledger_entries')
            ->selectRaw(
                'TRIM(coa_code) AS kode_coa,
                 LEFT(TRIM(coa_name), 200) AS nama_coa'
            )
            ->where('source_sheet', $sheet)
            ->whereRaw("NULLIF(TRIM(coa_code), '') IS NOT NULL")
            ->whereRaw("NULLIF(TRIM(coa_name), '') IS NOT NULL")
            ->distinct()
            ->get();

        $inserted = 0;

        foreach ($rows as $row) {
            $exists = DB::table('master_coa_mapping')
                ->where('kode_coa', $row->kode_coa)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('master_coa_mapping')->insert([
                'kode_coa' => $row->kode_coa,
                'nama_coa' => $row->nama_coa,
                'kategori' => $sheet,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $inserted++;
        }

        $this->info("Sinkronisasi selesai. COA baru: {$inserted}");

        return self::SUCCESS;
    }
}
