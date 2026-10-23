<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (
            !$user ||
            !Hash::check($data['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $token = $user->createToken(
            $data['device_name'] ?? 'api-client'
        );

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($request->user()),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        // Role default operator ditentukan oleh database.
        // Role tidak diambil dari request pengguna.
        $token = $user->createToken('api-client');

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil.',
            'token_type' => 'Bearer',
            'token' => $token->plainTextToken,
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }
}
