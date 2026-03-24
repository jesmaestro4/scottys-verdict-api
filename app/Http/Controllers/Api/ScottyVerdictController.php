<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NearbyListingsRequest;
use App\Http\Requests\SearchScottyVerdictRequest;
use App\Http\Requests\TopVerdictsRequest;
use App\Services\ListingSearchService;
use App\Services\ScottyVerdictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ScottyVerdictController extends Controller
{
    private const ERROR_SCHEMA = '#/components/schemas/ErrorResponse';

    public function __construct(
        private readonly ScottyVerdictService $verdicts,
        private readonly ListingSearchService $listings,
    ) {
    }

    #[OA\Get(
        path: '/api/verdicts/search',
        summary: 'Search verdicts for a specific make, model, and optional year.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Verdicts'],
        parameters: [
            new OA\Parameter(name: 'make', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'model', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Search results.', content: new OA\JsonContent(ref: '#/components/schemas/ScottyVerdictSearchResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function search(SearchScottyVerdictRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->verdicts->searchVerdicts(
                $request->string('make')->toString(),
                $request->string('model')->toString(),
                $request->integer('year') ?: null,
            ),
        ]);
    }

    #[OA\Get(
        path: '/api/verdicts/top-best',
        summary: 'Return top positively mentioned vehicles.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Verdicts'],
        parameters: [
            new OA\Parameter(name: 'vehicle_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10, maximum: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated verdicts.', content: new OA\JsonContent(ref: '#/components/schemas/ScottyVerdictPaginatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function topBest(TopVerdictsRequest $request): JsonResponse
    {
        return response()->json(
            $this->verdicts->topVerdicts(
                'good',
                max($request->integer('page'), 1),
                min(max($request->integer('page_size') ?: 10, 1), 20),
                $request->integer('vehicle_type_id') ?: null,
            )
        );
    }

    #[OA\Get(
        path: '/api/verdicts/top-worst',
        summary: 'Return top negatively mentioned vehicles.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Verdicts'],
        parameters: [
            new OA\Parameter(name: 'vehicle_type_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10, maximum: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated verdicts.', content: new OA\JsonContent(ref: '#/components/schemas/ScottyVerdictPaginatedResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function topWorst(TopVerdictsRequest $request): JsonResponse
    {
        return response()->json(
            $this->verdicts->topVerdicts(
                'bad',
                max($request->integer('page'), 1),
                min(max($request->integer('page_size') ?: 10, 1), 20),
                $request->integer('vehicle_type_id') ?: null,
            )
        );
    }

    #[OA\Get(
        path: '/api/verdicts/video-mentions',
        summary: 'Return video mentions for a make/model and optional year range.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Verdicts'],
        parameters: [
            new OA\Parameter(name: 'make', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'model', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'end_year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 200)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Video mentions response.'),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function videoMentions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'make' => ['required', 'string'],
            'model' => ['required', 'string'],
            'start_year' => ['nullable', 'integer', 'min:1886', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1886', 'max:2100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json(
            $this->verdicts->videoMentions(
                (string) $validated['make'],
                (string) $validated['model'],
                isset($validated['start_year']) ? (int) $validated['start_year'] : null,
                isset($validated['end_year']) ? (int) $validated['end_year'] : null,
                isset($validated['limit']) ? (int) $validated['limit'] : 50,
            )
        );
    }

    #[OA\Get(
        path: '/api/listings/near-me',
        summary: 'Find private party listings near a ZIP code or geo coordinates.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Listings'],
        parameters: [
            new OA\Parameter(name: 'zip', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'latitude', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'longitude', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\Parameter(name: 'radius', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 100, maximum: 100)),
            new OA\Parameter(name: 'page_size', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 50)),
            new OA\Parameter(name: 'make', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'model', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Nearby listings.', content: new OA\JsonContent(ref: '#/components/schemas/NearbyListingsResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function nearbyListings(NearbyListingsRequest $request): JsonResponse
    {
        return response()->json($this->listings->findNearbyListings($request->validated()));
    }
}
