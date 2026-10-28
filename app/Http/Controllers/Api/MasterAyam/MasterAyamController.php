<?php

namespace App\Http\Controllers\Api\MasterAyam;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterAyam\StoreMasterAyamRequest;
use App\Http\Requests\MasterAyam\UpdateMasterAyamRequest;
use App\Http\Resources\MasterAyamResource;
use App\Models\MasterAyam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterAyamController extends Controller
{
   
    public function index(Request $request): JsonResponse
    {
        $query = MasterAyam::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('kode_fase', 'ilike', "%{$s}%")
                ->orWhere('nama_fase', 'ilike', "%{$s}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 10), 100));

        $ayam = $query
            ->orderBy('kode_fase')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => MasterAyamResource::collection($ayam->items()),
            'pagination' => [
                'current_page' => $ayam->currentPage(),
                'per_page' => $ayam->perPage(),
                'total' => $ayam->total(),
                'last_page' => $ayam->lastPage(),
                'from' => $ayam->firstItem(),
                'to' => $ayam->lastItem(),
            ],
        ]);
    }


    public function store(StoreMasterAyamRequest $request): JsonResponse
    {
        $ayam = MasterAyam::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Master ayam berhasil dibuat.',
            'data' => new MasterAyamResource($ayam),
        ], 201);
    }

    public function show(MasterAyam $masterAyam): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MasterAyamResource($masterAyam),
        ]);
    }

    public function update(UpdateMasterAyamRequest $request, MasterAyam $masterAyam): JsonResponse
    {
        $masterAyam->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Master ayam berhasil diupdate.',
            'data' => new MasterAyamResource($masterAyam->fresh()),
        ]);
    }

    public function destroy(MasterAyam $masterAyam): JsonResponse
    {
        if ($masterAyam->transaksiAyamBulanan()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Fase sudah dipakai di transaksi. Nonaktifkan saja (is_active = false).',
            ], 409);
        }

        $masterAyam->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master ayam berhasil dihapus.',
        ]);
    }
}