<?php
require 'vendor/autoload.php';
require 'config/database.php';

// Use Laravel's database connection
$connection = DB::connection();

// Query good verdicts
$results = $connection->table('verdict_goodcars')
    ->whereRaw('LOWER(Make)=?', ['toyota'])
    ->whereRaw('LOWER(Model)=?', ['corolla'])
    ->select('StartYear', 'EndYear', DB::raw('COUNT(*) as cnt'))
    ->groupBy('StartYear', 'EndYear')
    ->orderBy('StartYear')
    ->get();

echo "=== GOOD VERDICTS ===\n";
foreach ($results as $row) {
    printf("StartYear: %s, EndYear: %s, Count: %d\n", $row->StartYear ?? 'NULL', $row->EndYear ?? 'NULL', $row->cnt);
}

// Query bad verdicts
$results = $connection->table('verdict_badcars')
    ->whereRaw('LOWER(Make)=?', ['toyota'])
    ->whereRaw('LOWER(Model)=?', ['corolla'])
    ->select('StartYear', 'EndYear', DB::raw('COUNT(*) as cnt'))
    ->groupBy('StartYear', 'EndYear')
    ->orderBy('StartYear')
    ->get();

echo "\n=== BAD VERDICTS ===\n";
foreach ($results as $row) {
    printf("StartYear: %s, EndYear: %s, Count: %d\n", $row->StartYear ?? 'NULL', $row->EndYear ?? 'NULL', $row->cnt);
}
