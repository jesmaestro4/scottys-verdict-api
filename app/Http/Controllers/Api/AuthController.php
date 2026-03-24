<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    private const ERROR_SCHEMA = '#/components/schemas/ErrorResponse';

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Authenticate a user with Laravel Sanctum cookie auth.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'remember', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated.', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Authenticated.',
            'user' => $request->user(),
        ]);
    }

    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Return the current authenticated user.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated user.', content: new OA\JsonContent(ref: '#/components/schemas/AuthResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Authenticated user.',
            'user' => $request->user(),
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Logout the current user and invalidate the session.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 204, description: 'Logged out.'),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }
}
