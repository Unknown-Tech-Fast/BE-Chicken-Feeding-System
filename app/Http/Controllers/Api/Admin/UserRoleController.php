<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function updateRole(
        Request $request,
        User $user
    ): JsonResponse {
        $validated = $request->validate([
            'role' => [
                'required',
                Rule::in(['admin', 'finance', 'operator']),
            ],
        ]);

        // Mencegah admin menghapus hak admin miliknya sendiri
        // melalui endpoint ini.
        if (
            $request->user()->is($user) &&
            $validated['role'] !== 'admin'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menurunkan role sendiri.',
            ], 422);
        }

        $user->role = $validated['role'];
        $user->save();

        // Cabut token agar perubahan role segera berlaku
        // pada sesi API berikutnya.
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diperbarui.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }
}
