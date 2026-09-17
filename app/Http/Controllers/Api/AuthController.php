<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Resources\ApiUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;

class AuthController extends Controller
{
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $key = 'api-login:'.hash('sha256', $request->string('email').'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Terlalu banyak percobaan masuk. Coba lagi nanti.'], 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        return app(Timebox::class)->call(function () use ($request, $key): JsonResponse {
            $dummyHash = Hash::make(Str::random(48));

            return DB::transaction(function () use ($request, $key, $dummyHash): JsonResponse {
                // SQLite ignores FOR UPDATE: acquire its write lock before reading credentials.
                if (DB::connection()->getDriverName() === 'sqlite') {
                    DB::table('users')->where('email', $request->validated('email'))->update(['id' => DB::raw('"id"')]);
                }
                $user = User::where('email', $request->validated('email'))->lockForUpdate()->first();
                $valid = Hash::check($request->validated('password'), $user?->password ?? $dummyHash);
                if (! $user || ! $valid) {
                    return response()->json(['message' => 'Email atau kata sandi salah.'], 401, ['Cache-Control' => 'private, no-store']);
                }
                $expiresAt = now()->addDay();
                $token = $user->createToken($request->validated('device_name'), ['mobile:read'], $expiresAt);
                RateLimiter::clear($key);

                return response()->json([
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt->toIso8601String(),
                    'user' => (new ApiUserResource($user))->resolve($request),
                ], 200, ['Cache-Control' => 'private, no-store']);
            }, 3);
        }, 200000);
    }

    public function me(Request $request): ApiUserResource
    {
        return new ApiUserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
