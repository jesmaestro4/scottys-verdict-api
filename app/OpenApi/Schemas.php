<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VideoItem',
    properties: [
        new OA\Property(property: 'video_id', type: 'string'),
        new OA\Property(property: 'comment', type: 'string', nullable: true),
        new OA\Property(property: 'video_title', type: 'string'),
        new OA\Property(property: 'timestamp', type: 'string', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ImageItem',
    properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'url', type: 'string', format: 'uri'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PriceRange',
    properties: [
        new OA\Property(property: 'min', type: 'integer', nullable: true),
        new OA\Property(property: 'max', type: 'integer', nullable: true),
        new OA\Property(property: 'listing_count', type: 'integer'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ScottyCarObject',
    properties: [
        new OA\Property(property: 'guid', type: 'string'),
        new OA\Property(property: 'make', type: 'string'),
        new OA\Property(property: 'model', type: 'string'),
        new OA\Property(property: 'year', type: 'integer', nullable: true),
        new OA\Property(property: 'vehicle_type_id', type: 'integer', nullable: true),
        new OA\Property(property: 'vehicle_type', type: 'string', nullable: true),
        new OA\Property(property: 'manufacturer_name', type: 'string', nullable: true),
        new OA\Property(property: 'trim', type: 'string', nullable: true),
        new OA\Property(property: 'body_class', type: 'string', nullable: true),
        new OA\Property(property: 'drive_type', type: 'string', nullable: true),
        new OA\Property(property: 'fuel_type_primary', type: 'string', nullable: true),
        new OA\Property(property: 'engine_cylinders', type: 'string', nullable: true),
        new OA\Property(property: 'transmission_style', type: 'string', nullable: true),
        new OA\Property(property: 'tobuy', type: 'boolean'),
        new OA\Property(property: 'total_mentions', type: 'integer'),
        new OA\Property(property: 'videos', type: 'array', items: new OA\Items(ref: '#/components/schemas/VideoItem')),
        new OA\Property(property: 'images', type: 'array', items: new OA\Items(ref: '#/components/schemas/ImageItem')),
        new OA\Property(property: 'price_range', ref: '#/components/schemas/PriceRange'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'page', type: 'integer'),
        new OA\Property(property: 'page_size', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ScottyVerdictSearchResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ScottyCarObject')),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ScottyVerdictPaginatedResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ScottyCarObject')),
        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ListingItem',
    properties: [
        new OA\Property(property: 'listing_id', type: 'string'),
        new OA\Property(property: 'heading', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'integer', nullable: true),
        new OA\Property(property: 'miles', type: 'integer', nullable: true),
        new OA\Property(property: 'distance_miles', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'vdp_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'seller_type', type: 'string', nullable: true),
        new OA\Property(property: 'inventory_type', type: 'string', nullable: true),
        new OA\Property(property: 'source', type: 'string', nullable: true),
        new OA\Property(property: 'zip', type: 'string', nullable: true),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true),
        new OA\Property(
            property: 'build',
            properties: [
                new OA\Property(property: 'year', type: 'integer', nullable: true),
                new OA\Property(property: 'make', type: 'string', nullable: true),
                new OA\Property(property: 'model', type: 'string', nullable: true),
                new OA\Property(property: 'trim', type: 'string', nullable: true),
                new OA\Property(property: 'car_guid', type: 'string', nullable: true),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'NearbyListingsResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ListingItem')),
        new OA\Property(
            property: 'meta',
            properties: [
                new OA\Property(property: 'count', type: 'integer'),
                new OA\Property(property: 'radius_miles', type: 'integer'),
                new OA\Property(property: 'source', type: 'string'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuthResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(
            property: 'user',
            properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ],
            type: 'object'
        ),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string'),
    ],
    type: 'object'
)]
class Schemas
{
}
