<?php
/**
 * Check park database mapping
 */

require __DIR__ . '/../app/bootstrap.php';

use CampsiteAgent\Repositories\ParkRepository;

header('Content-Type: text/plain');

echo "=== Park Database Mapping Check ===\n\n";

$parkRepo = new ParkRepository();
$parks = $parkRepo->listAll();

echo "All parks in database:\n";
echo str_repeat("-", 80) . "\n";
printf("%-5s %-30s %-15s %-15s %s\n", "ID", "Name", "park_number", "external_id", "active");
echo str_repeat("-", 80) . "\n";

foreach ($parks as $park) {
    printf(
        "%-5s %-30s %-15s %-15s %s\n",
        $park['id'],
        substr($park['name'], 0, 30),
        $park['park_number'] ?? 'NULL',
        $park['external_id'] ?? 'NULL',
        $park['active'] ? 'YES' : 'NO'
    );
}

echo "\n\n=== Specific Park Check ===\n";
echo "Park ID 13 (Chino Hills SP):\n";

$chinoHills = null;
foreach ($parks as $park) {
    if ((int)$park['id'] === 13) {
        $chinoHills = $park;
        break;
    }
}

if ($chinoHills) {
    echo "  ID: " . $chinoHills['id'] . "\n";
    echo "  Name: " . $chinoHills['name'] . "\n";
    echo "  park_number: " . ($chinoHills['park_number'] ?? 'NULL') . "\n";
    echo "  external_id: " . ($chinoHills['external_id'] ?? 'NULL') . "\n";
    echo "  active: " . ($chinoHills['active'] ? 'YES' : 'NO') . "\n";
    echo "\n";
    echo "  Expected park_number: 627\n";
    echo "  Actual park_number: " . ($chinoHills['park_number'] ?? 'NULL') . "\n";
    if (($chinoHills['park_number'] ?? '') !== '627') {
        echo "  ⚠️  MISMATCH! Park 13 should have park_number 627\n";
    } else {
        echo "  ✅ Correct mapping\n";
    }
} else {
    echo "  ❌ Park ID 13 not found in database!\n";
}

echo "\n\nPark ID that has park_number 635:\n";
foreach ($parks as $park) {
    if (($park['park_number'] ?? '') === '635') {
        echo "  ID: " . $park['id'] . "\n";
        echo "  Name: " . $park['name'] . "\n";
        echo "  park_number: " . $park['park_number'] . "\n";
        break;
    }
}

