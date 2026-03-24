<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListingRepository
{
    public function priceRangeForCar(string $carGuid): array
    {
        $row = DB::table('listing_listing')
            ->where('car_GUID', $carGuid)
            ->whereNotNull('Price')
            ->selectRaw('MIN(Price) as min_price, MAX(Price) as max_price, COUNT(*) as listing_count')
            ->first();

        return [
            'min' => $row?->min_price !== null ? (int) $row->min_price : null,
            'max' => $row?->max_price !== null ? (int) $row->max_price : null,
            'listing_count' => $row?->listing_count !== null ? (int) $row->listing_count : 0,
            'currency' => 'USD',
        ];
    }

    public function cacheListings(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('listing_listing')->updateOrInsert(
                ['ListingId' => $row['ListingId']],
                $row,
            );
        }
    }

    public function searchNearby(array $criteria, int $limit = 50): Collection
    {
        $radius = min((int) ($criteria['radius'] ?? 100), 100);

        $query = DB::table('listing_listing')
            ->select('listing_listing.*')
            ->whereNotNull('Price');

        if (! empty($criteria['make'])) {
            $query->whereRaw('LOWER(BuildMake) = ?', [strtolower($criteria['make'])]);
        }

        if (! empty($criteria['model'])) {
            $query->whereRaw('LOWER(BuildModel) = ?', [strtolower($criteria['model'])]);
        }

        if (! empty($criteria['year'])) {
            $query->where('BuildYear', (int) $criteria['year']);
        }

        if (! empty($criteria['latitude']) && ! empty($criteria['longitude'])) {
            $latitude = (float) $criteria['latitude'];
            $longitude = (float) $criteria['longitude'];

            $distanceSql = '3959 * acos(cos(radians(?)) * cos(radians(COALESCE(DealerLatitude, McLatitude))) * cos(radians(COALESCE(DealerLongitude, McLongitude)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(DealerLatitude, McLatitude))))';

            $query->selectRaw($distanceSql.' as distance_miles', [$latitude, $longitude, $latitude])
                ->havingRaw('distance_miles <= ?', [$radius])
                ->orderBy('Price')
                ->orderBy('distance_miles');
        } elseif (! empty($criteria['zip'])) {
            $query->where(function ($builder) use ($criteria): void {
                $builder->where('DealerZip', $criteria['zip'])
                    ->orWhere('McZip', $criteria['zip']);
            })
                ->selectRaw('NULL as distance_miles')
                ->orderBy('Price');
        } else {
            $query->selectRaw('NULL as distance_miles')
                ->orderBy('Price');
        }

        return $query->limit($limit)->get()->map(fn (object $row): array => (array) $row);
    }
}
