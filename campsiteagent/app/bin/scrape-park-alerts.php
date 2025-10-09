<?php
/**
 * Scrape park alerts for all parks
 * This script scrapes alerts from all park websites and stores them in the database
 */

require_once __DIR__ . '/../bootstrap.php';

// Manually include the new classes (in case autoloader isn't updated)
require_once __DIR__ . '/../src/Repositories/ParkAlertRepository.php';
require_once __DIR__ . '/../src/Services/ParkAlertScraperService.php';

use CampsiteAgent\Repositories\ParkAlertRepository;
use CampsiteAgent\Services\ParkAlertScraperService;
use CampsiteAgent\Infrastructure\HttpClient;

echo "=== Park Alert Scraping ===\n\n";

try {
    $pdo = \CampsiteAgent\Infrastructure\Database::getConnection();
    $alertRepository = new ParkAlertRepository($pdo);
    $httpClient = new HttpClient();
    $scraperService = new ParkAlertScraperService($alertRepository, $httpClient);
    
    // Get all parks with website URLs
    $stmt = $pdo->query('SELECT id, name, website_url FROM parks WHERE website_url IS NOT NULL AND website_url != "" ORDER BY name');
    $parks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($parks) . " parks with website URLs\n\n";
    
    $totalAlerts = 0;
    $parksWithAlerts = 0;
    
    foreach ($parks as $park) {
        echo "Scraping alerts for {$park['name']}...\n";
        
        try {
            $alerts = $scraperService->scrapeParkAlerts($park['id'], $park['website_url']);
            $alertCount = count($alerts);
            
            if ($alertCount > 0) {
                echo "  ✅ Found {$alertCount} alerts\n";
                $parksWithAlerts++;
                $totalAlerts += $alertCount;
                
                // Show first few alerts
                foreach (array_slice($alerts, 0, 3) as $alert) {
                    echo "    - [{$alert['severity']}] {$alert['title']}\n";
                }
                if ($alertCount > 3) {
                    echo "    ... and " . ($alertCount - 3) . " more\n";
                }
            } else {
                echo "  ℹ️  No alerts found\n";
            }
            
        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
        
        // Small delay to be respectful to the server
        usleep(500000); // 0.5 seconds
    }
    
    echo "=== Scraping Complete ===\n";
    echo "Total alerts found: {$totalAlerts}\n";
    echo "Parks with alerts: {$parksWithAlerts}\n";
    echo "Parks processed: " . count($parks) . "\n\n";
    
    // Show alert statistics
    $stats = $alertRepository->getAlertStats();
    echo "Database Statistics:\n";
    echo "  Total alerts: {$stats['total_alerts']}\n";
    echo "  Active alerts: {$stats['active_alerts']}\n";
    echo "  Critical: {$stats['critical_alerts']}\n";
    echo "  Warning: {$stats['warning_alerts']}\n";
    echo "  Info: {$stats['info_alerts']}\n";
    
    echo "\n✅ Park alert scraping completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
