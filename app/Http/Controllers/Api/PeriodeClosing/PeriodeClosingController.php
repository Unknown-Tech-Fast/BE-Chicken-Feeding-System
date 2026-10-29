<?php

namespace App\Http\Controllers\Api\PeriodeClosing;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodeClosing\StorePeriodeClosingRequest;
use App\Http\Requests\PeriodeClosing\UpdatePeriodeClosingRequest;
use App\Http\Resources\PeriodeClosingResource;
use App\Services\PeriodeClosingService;
use App\Models\PeriodeClosing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodeClosingController extends Controller
{
 
    public function index(Request $request): JsonResponse
    {
        $query = PeriodeClosing::query();

        if ($request->filled('tahun')) {
            $query->where('tahun', $request->integer('tahun'));
        }

        if ($request->filled('bulan')) {
            $query->where('bulan', $request->integer('bulan'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = max(1, min($request->integer('per_page', 10), 100));

        $periode = $query
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => PeriodeClosingResource::collection($periode->items()),
            'pagination' => [
                'current_page' => $periode->currentPage(),
                'per_page' => $periode->perPage(),
                'total' => $periode->total(),
                'last_page' => $periode->lastPage(),
                'from' => $periode->firstItem(),
                'to' => $periode->lastItem(),
            ],
        ]);
    }


    public function store(StorePeriodeClosingRequest $request): JsonResponse
    {
        $periode = PeriodeClosing::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Periode closing berhasil dibuat.',
            'data' => new PeriodeClosingResource($periode),
        ], 201);
    }

    public function show(PeriodeClosing $periodeClosing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PeriodeClosingResource($periodeClosing),
        ]);
    }

    public function update(
        UpdatePeriodeClosingRequest $request,
        PeriodeClosing $periodeClosing
    ): JsonResponse {
        $periodeClosing->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Periode closing berhasil diupdate.',
            'data' => new PeriodeClosingResource($periodeClosing->fresh()),
        ]);
    }

    public function destroy(PeriodeClosing $periodeClosing): JsonResponse
    {
        if ($periodeClosing->transaksiAyamBulanan()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Periode sudah digunakan transaksi ayam dan tidak bisa dihapus.',
            ], 409);
        }

        $periodeClosing->delete();

        return response()->json([
            'success' => true,
            'message' => 'Periode closing berhasil dihapus.',
        ]);
    }

    public function aktif(PeriodeClosingService $service): JsonResponse
    {
        $periode = $service->getPeriodeAktif();

        return response()->json([
            'success' => true,
            'message' => 'Periode aktif berhasil diambil.',
            'data' => new PeriodeClosingResource($periode),
        ]);
    }

 
    public function closing(
        Request $request,
        PeriodeClosing $periodeClosing,
        PeriodeClosingService $service
    ): JsonResponse {
        $periode = $service->closing(
            $periodeClosing->id,
            (int) ($request->user()?->id ?? 0)
        );

        return response()->json([
            'success' => true,
            'message' => 'Periode berhasil ditutup.',
            'data' => new PeriodeClosingResource($periode),
        ]);
    }

}