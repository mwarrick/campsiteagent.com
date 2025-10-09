<?php
/**
 * Test script for park alert scraping
 * This script tests the park alert scraping functionality
 */

require_once __DIR__ . '/../bootstrap.php';

// Manually include the new classes (in case autoloader isn't updated)
require_once __DIR__ . '/../src/Repositories/ParkAlertRepository.php';
require_once __DIR__ . '/../src/Services/ParkAlertScraperService.php';

use CampsiteAgent\Repositories\ParkAlertRepository;
use CampsiteAgent\Services\ParkAlertScraperService;
use CampsiteAgent\Infrastructure\HttpClient;

echo "=== Testing Park Alert Scraping ===\n\n";

try {
    $pdo = \CampsiteAgent\Infrastructure\Database::getConnection();
    $alertRepository = new ParkAlertRepository($pdo);
    $httpClient = new HttpClient();
    $scraperService = new ParkAlertScraperService($alertRepository, $httpClient);
    
    // Test with Anza-Borrego Desert State Park (the example you provided)
    echo "Testing with Anza-Borrego Desert State Park...\n";
    
    $stmt = $pdo->prepare('SELECT id, name, website_url FROM parks WHERE name LIKE "%Anza-Borrego%" LIMIT 1');
    $stmt->execute();
    $park = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$park) {
        echo "❌ Anza-Borrego Desert State Park not found in database\n";
        exit(1);
    }
    
    echo "Found park: {$park['name']} (ID: {$park['id']})\n";
    echo "Website: {$park['website_url']}\n\n";
    
    // Scrape alerts
    $alerts = $scraperService->scrapeParkAlerts($park['id'], $park['website_url']);
    
    echo "Found " . count($alerts) . " alerts:\n";
    foreach ($alerts as $i => $alert) {
        echo "  " . ($i + 1) . ". [{$alert['severity']}] {$alert['alert_type']}: {$alert['title']}\n";
    }
    
    // Check what was saved to database
    echo "\nChecking database...\n";
    $savedAlerts = $alertRepository->getActiveAlertsForPark($park['id']);
    echo "Saved " . count($savedAlerts) . " alerts to database:\n";
    
    foreach ($savedAlerts as $alert) {
        echo "  - [{$alert['severity']}] {$alert['alert_type']}: {$alert['title']}\n";
        if ($alert['description']) {
            echo "    Description: " . substr($alert['description'], 0, 100) . "...\n";
        }
        echo "    Created: {$alert['created_at']}\n\n";
    }
    
    // Show alert statistics
    $stats = $alertRepository->getAlertStats();
    echo "Alert Statistics:\n";
    echo "  Total alerts: {$stats['total_alerts']}\n";
    echo "  Active alerts: {$stats['active_alerts']}\n";
    echo "  Critical: {$stats['critical_alerts']}\n";
    echo "  Warning: {$stats['warning_alerts']}\n";
    echo "  Info: {$stats['info_alerts']}\n";
    
    echo "\n✅ Park alert scraping test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
