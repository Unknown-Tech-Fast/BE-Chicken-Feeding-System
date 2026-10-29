<?php

namespace App\Services;

use App\Models\PeriodeClosing;
use App\Models\TransaksiAyamBulanan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransaksiAyamBulananService
{
    public function store(array $data): TransaksiAyamBulanan
    {
        return DB::transaction(function () use ($data) {
            $periode = PeriodeClosing::whereKey($data['periode_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->pastikanPeriodeTerbuka($periode);

            // Jika ada saldo dari periode sebelumnya, gunakan saldo tersebut.
            $periodeSebelumnya = PeriodeClosing::where(function ($query) use ($periode) {
                $query->where('tahun', '<', $periode->tahun)
                    ->orWhere(function ($query) use ($periode) {
                        $query->where('tahun', $periode->tahun)
                            ->where('bulan', '<', $periode->bulan);
                    });
            })
                ->orderByDesc('tahun')
                ->orderByDesc('bulan')
                ->first();

            if ($periodeSebelumnya) {
                $saldoSebelumnya = TransaksiAyamBulanan::where(
                    'periode_id',
                    $periodeSebelumnya->id
                )
                    ->where('kandang_id', $data['kandang_id'])
                    ->where('master_ayam_id', $data['master_ayam_id'])
                    ->where('flock_no', $data['flock_no'] ?? '')
                    ->first();

                if ($saldoSebelumnya) {
                    if ($periodeSebelumnya->status !== 'CLOSED') {
                        throw ValidationException::withMessages([
                            'periode_id' => 'Periode sebelumnya harus ditutup terlebih dahulu.',
                        ]);
                    }

                    $data['so_ekor'] = $saldoSebelumnya->sa_ekor;
                }
            }

            $data['so_ekor'] ??= 0;

            $transaksi = TransaksiAyamBulanan::create($data);

            $this->hitungSaldoAkhir($transaksi);

            return $transaksi->fresh();
        });
    }

    public function hitungSaldoAkhir(
        TransaksiAyamBulanan $transaksi
    ): void {
        $sa = (int) $transaksi->so_ekor
            + (int) $transaksi->in_ekor
            - (int) $transaksi->out_ekor
            - (int) $transaksi->mati_ekor
            - (int) $transaksi->jual_ekor
            + (int) $transaksi->koreksi_ekor;

        if ($sa < 0) {
            throw ValidationException::withMessages([
                'sa_ekor' => 'Saldo akhir tidak boleh negatif.',
            ]);
        }

        $transaksi->sa_ekor = $sa;
        $transaksi->save();
    }

    private function pastikanPeriodeTerbuka(
        PeriodeClosing $periode
    ): void {
        if ($periode->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'periode_id' => 'Transaksi hanya dapat dilakukan pada periode OPEN.',
            ]);
        }
    }

    public function update(
        TransaksiAyamBulanan $transaksi,
        array $data
    ): TransaksiAyamBulanan {
        return DB::transaction(function () use ($transaksi, $data) {
            $periode = PeriodeClosing::whereKey($transaksi->periode_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->pastikanPeriodeTerbuka($periode);

            // Saldo awal tidak diubah melalui update transaksi biasa.
            unset($data['so_ekor']);

            $transaksi->update($data);

            $this->hitungSaldoAkhir($transaksi);

            return $transaksi->fresh();
        });
    }

    public function delete(TransaksiAyamBulanan $transaksi): void
    {
        DB::transaction(function () use ($transaksi) {
            $periode = PeriodeClosing::whereKey($transaksi->periode_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->pastikanPeriodeTerbuka($periode);

            if ($transaksi->alokasiBiaya()->exists()) {
                throw ValidationException::withMessages([
                    'transaksi' => 'Transaksi masih memiliki alokasi biaya.',
                ]);
            }

            $transaksi->delete();
        });
    }
}
