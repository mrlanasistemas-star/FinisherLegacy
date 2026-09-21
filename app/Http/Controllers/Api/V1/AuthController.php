<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Token-based auth for API clients (mobile apps, external sites). The web
 * app keeps using Fortify's session-based auth directly — this controller
 * only adds the token issuance/revocation layer on top of the exact same
 * user-creation action Fortify's own registration form uses.
 */
class AuthController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly CreateNewUser $creator) {}

    public function register(Request $request): JsonResponse
    {
        $user = $this->creator->create($request->all());

        $token = $user->createToken('api')->plainTextToken;

        return $this->respond([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Tu Legacy comienza aquí.', status: 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Auth::guard('web')->validate([
            'email' => $request->string('email'),
            'password' => $request->string('password'),
        ])) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->respond([
            'user' => new UserResource($user),
            'token' => $token,
        ], null);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->sanctumUser($request)->currentAccessToken()->delete();

        return $this->respond(null, 'Sesión cerrada.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $user->loadMissing(['legacyId', 'athlete']);

        return $this->respond(new UserResource($user));
    }
}
