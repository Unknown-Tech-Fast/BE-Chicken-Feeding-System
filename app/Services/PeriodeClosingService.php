<?php

namespace App\Services;

use App\Models\PeriodeClosing;
use App\Models\TransaksiAyamBulanan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodeClosingService
{
    public function closing(int $periodeId, int $userId): PeriodeClosing
    {
        return DB::transaction(function () use ($periodeId, $userId) {
            $periode = PeriodeClosing::whereKey($periodeId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($periode->status !== 'OPEN') {
                throw ValidationException::withMessages([
                    'periode' => 'Periode sudah ditutup.',
                ]);
            }

            $transaksiList = TransaksiAyamBulanan::where(
                'periode_id',
                $periode->id
            )->lockForUpdate()->get();

            // Hitung dan simpan saldo akhir semua transaksi.
            foreach ($transaksiList as $transaksi) {
                $sa = (int) $transaksi->so_ekor
                    + (int) $transaksi->in_ekor
                    - (int) $transaksi->out_ekor
                    - (int) $transaksi->mati_ekor
                    - (int) $transaksi->jual_ekor
                    + (int) $transaksi->koreksi_ekor;

                if ($sa < 0) {
                    throw ValidationException::withMessages([
                        'sa_ekor' => "Saldo akhir negatif pada transaksi ID {$transaksi->id}.",
                    ]);
                }

                $transaksi->sa_ekor = $sa;
                $transaksi->save();
            }

            // Tutup periode.
            $periode->status = 'CLOSED';
            $periode->locked_at = now();
            $periode->locked_by = $userId;
            $periode->save();

            // Siapkan periode bulan berikutnya.
            $tanggalBerikutnya = now()->setDate(
                (int) $periode->tahun,
                (int) $periode->bulan,
                1
            )->addMonth();

            $periodeBerikutnya = PeriodeClosing::firstOrCreate(
                [
                    'tahun' => $tanggalBerikutnya->year,
                    'bulan' => $tanggalBerikutnya->month,
                ],
                [
                    'status' => 'OPEN',
                    'total_biaya_operasional' => 0,
                ]
            );

            // Salin saldo akhir menjadi saldo awal bulan berikutnya.
            foreach ($transaksiList as $transaksi) {
                TransaksiAyamBulanan::firstOrCreate(
                    [
                        'periode_id' => $periodeBerikutnya->id,
                        'lokasi_id' => $transaksi->lokasi_id,
                        'kandang_id' => $transaksi->kandang_id,
                        'master_ayam_id' => $transaksi->master_ayam_id,
                        'flock_no' => $transaksi->flock_no,
                    ],
                    [
                        'so_ekor' => $transaksi->sa_ekor,
                        'in_ekor' => 0,
                        'out_ekor' => 0,
                        'mati_ekor' => 0,
                        'jual_ekor' => 0,
                        'koreksi_ekor' => 0,
                        'sa_ekor' => $transaksi->sa_ekor,
                        'umur_minggu' => $transaksi->umur_minggu,
                    ]
                );
            }

            return $periode->fresh();
        });
    }
}
