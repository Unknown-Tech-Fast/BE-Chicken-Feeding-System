<?php

namespace App\Http\Controllers\Api\TransaksiAyamBulanan;

use App\Http\Controllers\Controller;
use App\Services\TransaksiAyamBulananService;
use App\Http\Requests\TransaksiAyamBulanan\StoreTransaksiAyamBulananRequest;
use App\Http\Requests\TransaksiAyamBulanan\UpdateTransaksiAyamBulananRequest;
use App\Http\Resources\TransaksiAyamBulananResource;
use App\Models\TransaksiAyamBulanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransaksiAyamBulananController extends Controller
{
    private const RELATIONS = ['periode', 'kandang.lokasi', 'masterAyam'];
   
    public function index(Request $request): JsonResponse
    {
        $query = TransaksiAyamBulanan::query()->with(self::RELATIONS);

        foreach (['periode_id', 'kandang_id', 'master_ayam_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->integer($field));
            }
        }

        if ($request->filled('flock_no')) {
            $query->where('flock_no', 'ilike', '%' . $request->flock_no . '%');
        }

        $perPage = max(1, min($request->integer('per_page', 25), 200));

        $transaksi = $query
            ->orderByDesc('periode_id')
            ->orderBy('kandang_id')
            ->orderBy('master_ayam_id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => TransaksiAyamBulananResource::collection($transaksi->items()),
            'pagination' => [
                'current_page' => $transaksi->currentPage(),
                'per_page' => $transaksi->perPage(),
                'total' => $transaksi->total(),
                'last_page' => $transaksi->lastPage(),
                'from' => $transaksi->firstItem(),
                'to' => $transaksi->lastItem(),
            ],
        ]);
    }


    public function store(
        StoreTransaksiAyamBulananRequest $request,
        TransaksiAyamBulananService $service
    ): JsonResponse {
        $transaksi = $service->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transaksi ayam bulanan berhasil dibuat.',
            'data' => new TransaksiAyamBulananResource(
                $transaksi->load(self::RELATIONS)
            ),
        ], 201);
    }
    
    public function show(TransaksiAyamBulanan $transaksiAyamBulanan): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new TransaksiAyamBulananResource($transaksiAyamBulanan->load(self::RELATIONS)),
        ]);
    }

    public function update(
        UpdateTransaksiAyamBulananRequest $request,
        TransaksiAyamBulanan $transaksiAyamBulanan,
        TransaksiAyamBulananService $service
    ): JsonResponse {
        $transaksi = $service->update(
            $transaksiAyamBulanan,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaksi ayam bulanan berhasil diupdate.',
            'data' => new TransaksiAyamBulananResource(
                $transaksi->load(self::RELATIONS)
            ),
        ]);
    }

    public function destroy(
        TransaksiAyamBulanan $transaksiAyamBulanan,
        TransaksiAyamBulananService $service
    ): JsonResponse {
        $service->delete($transaksiAyamBulanan);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi ayam bulanan berhasil dihapus.',
        ]);
    }
}