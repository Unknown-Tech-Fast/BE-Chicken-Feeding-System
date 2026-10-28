<?php

namespace App\Http\Controllers\Api\MasterLokasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterLokasi\StoreMasterLokasiRequest;
use App\Http\Requests\MasterLokasi\UpdateMasterLokasiRequest;
use App\Http\Resources\MasterLokasiResource;
use App\Models\MasterLokasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterLokasiController extends Controller
{
   
    public function index(Request $request): JsonResponse
    {
        $query = MasterLokasi::query()
            ->withCount('kandang');

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('kode_lokasi', 'ilike', "%{$search}%")
                    ->orWhere('nama_lokasi', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = max(1, min($request->integer('per_page', 10), 100));

        $lokasi = $query
            ->orderBy('kode_lokasi')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => MasterLokasiResource::collection($lokasi->items()),
            'pagination' => [
                'current_page' => $lokasi->currentPage(),
                'per_page' => $lokasi->perPage(),
                'total' => $lokasi->total(),
                'last_page' => $lokasi->lastPage(),
                'from' => $lokasi->firstItem(),
                'to' => $lokasi->lastItem(),
            ],
        ]);
    }


    public function store(
        StoreMasterLokasiRequest $request
    ): JsonResponse {
        $lokasi = MasterLokasi::create(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Master lokasi berhasil dibuat.',
            'data' => new MasterLokasiResource(
                $lokasi->loadCount('kandang')
            ),
        ], 201);
    }

    public function show(
        MasterLokasi $masterLokasi
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new MasterLokasiResource(
                $masterLokasi->loadCount('kandang')
            ),
        ]);
    }

    public function update(
        UpdateMasterLokasiRequest $request,
        MasterLokasi $masterLokasi
    ): JsonResponse {
        $masterLokasi->update(
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Master lokasi berhasil diupdate.',
            'data' => new MasterLokasiResource(
                $masterLokasi->fresh()->loadCount('kandang')
            ),
        ]);
    }

    public function destroy(
        MasterLokasi $masterLokasi
    ): JsonResponse {
        if (
            $masterLokasi->kandang()->exists()
            || $masterLokasi->journalDetails()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi sudah dipakai di kandang atau jurnal. Nonaktifkan saja (is_active = false).',
            ], 409);
        }

        $masterLokasi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master lokasi berhasil dihapus.',
        ]);
    }
}