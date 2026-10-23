<?php

namespace App\Http\Controllers\Api\MasterKandang;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterKandang\StoreMasterKandangRequest;
use App\Http\Requests\MasterKandang\UpdateMasterKandangRequest;
use App\Http\Resources\MasterKandangResource;
use App\Models\MasterKandang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterKandangController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MasterKandang::query()->with('lokasi');

        if ($request->filled('search')) {
            $query->where('kode_kandang', 'ilike', '%' . $request->search . '%');
        }

        if ($request->filled('lokasi_id')) {
            $query->where('lokasi_id', $request->integer('lokasi_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'success' => true,
            'data' => MasterKandangResource::collection(
                $query->orderBy('lokasi_id')->orderBy('kode_kandang')->get()
            ),
        ]);
    }

    public function store(StoreMasterKandangRequest $request): JsonResponse
    {
        $kandang = MasterKandang::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Master kandang berhasil dibuat.',
            'data' => new MasterKandangResource($kandang->load('lokasi')),
        ], 201);
    }

    public function show(MasterKandang $masterKandang): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new MasterKandangResource($masterKandang->load('lokasi')),
        ]);
    }

    public function update(UpdateMasterKandangRequest $request, MasterKandang $masterKandang): JsonResponse
    {
        $masterKandang->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Master kandang berhasil diupdate.',
            'data' => new MasterKandangResource($masterKandang->fresh('lokasi')),
        ]);
    }

    public function destroy(MasterKandang $masterKandang): JsonResponse
    {
        if (
            $masterKandang->transaksiAyamBulanan()->exists()
            || $masterKandang->journalDetails()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Kandang sudah dipakai di transaksi atau jurnal. Nonaktifkan saja (is_active = false).',
            ], 409);
        }

        $masterKandang->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master kandang berhasil dihapus.',
        ]);
    }
}