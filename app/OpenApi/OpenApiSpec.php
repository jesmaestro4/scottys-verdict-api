<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Scotty Verdict API',
    description: 'Laravel API for Scotty car verdicts, cached vehicle enrichment, and nearby private-party listings.'
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Local development server')]
#[OA\SecurityScheme(
    securityScheme: 'sanctumCookieAuth',
    type: 'apiKey',
    in: 'cookie',
    name: 'laravel_session',
    description: 'Laravel Sanctum session cookie. Obtain with GET /sanctum/csrf-cookie and POST /api/auth/login.'
)]
#[OA\SecurityScheme(
    securityScheme: 'xsrfHeader',
    type: 'apiKey',
    in: 'header',
    name: 'X-XSRF-TOKEN',
    description: 'CSRF token header required for stateful SPA requests.'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerToken',
    type: 'http',
    scheme: 'bearer',
    description: 'Sanctum Personal Access Token. Create a token via login, then use it in the Authorization header: Bearer {token}'
)]
#[OA\Tag(name: 'Auth')]
#[OA\Tag(name: 'Verdicts')]
#[OA\Tag(name: 'Listings')]
#[OA\Tag(name: 'Images')]
class OpenApiSpec
{
}
