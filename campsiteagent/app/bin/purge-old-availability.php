#!/usr/bin/env php
<?php
/**
 * Purge old site_availability records
 *
 * Deletes site_availability rows with created_at older than N days (default 7),
 * in batches to avoid long locks.
 *
 * Usage:
 *  php purge-old-availability.php [--days=7] [--batch=5000] [--dry-run] [--verbose]
 */

require __DIR__ . '/../bootstrap.php';

use CampsiteAgent\Infrastructure\Database;

$options = getopt('', [
    'days::',
    'batch::',
    'dry-run',
    'verbose',
    'help'
]);

if (isset($options['help'])) {
    echo "Purge old site_availability records\n";
    echo "Usage: php purge-old-availability.php [--days=7] [--batch=5000] [--dry-run] [--verbose]\n";
    exit(0);
}

$days = isset($options['days']) ? max(1, (int)$options['days']) : 7;
$batchSize = isset($options['batch']) ? max(100, (int)$options['batch']) : 5000;
$dryRun = isset($options['dry-run']);
$verbose = isset($options['verbose']);

$log = function(string $msg) use ($verbose) {
    if ($verbose) echo $msg . "\n";
};

try {
    $pdo = Database::getConnection();

    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    echo "Purging site_availability older than {$days} day(s) (created_at < {$cutoff})" . ($dryRun ? " [DRY-RUN]" : "") . "\n";

    // Count total candidates
    $stmtCount = $pdo->prepare('SELECT COUNT(*) FROM site_availability WHERE created_at < :cutoff');
    $stmtCount->execute([':cutoff' => $cutoff]);
    $total = (int)$stmtCount->fetchColumn();
    echo "Candidates: {$total}\n";

    if ($total === 0) {
        exit(0);
    }

    if ($dryRun) {
        // Show a small sample of IDs/dates
        $stmtSample = $pdo->prepare('SELECT id, site_id, date, created_at FROM site_availability WHERE created_at < :cutoff ORDER BY id ASC LIMIT 10');
        $stmtSample->execute([':cutoff' => $cutoff]);
        $rows = $stmtSample->fetchAll();
        foreach ($rows as $r) {
            $log("  #{$r['id']} site {$r['site_id']} date {$r['date']} created_at {$r['created_at']}");
        }
        exit(0);
    }

    $deleted = 0;
    while (true) {
        // Collect a batch of IDs to delete
        $stmtIds = $pdo->prepare('SELECT id FROM site_availability WHERE created_at < :cutoff ORDER BY id ASC LIMIT :lim');
        $stmtIds->bindValue(':cutoff', $cutoff, PDO::PARAM_STR);
        $stmtIds->bindValue(':lim', $batchSize, PDO::PARAM_INT);
        $stmtIds->execute();
        $ids = array_column($stmtIds->fetchAll(), 'id');

        if (empty($ids)) break;

        // Delete this batch
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sqlDel = "DELETE FROM site_availability WHERE id IN ({$in})";
        $stmtDel = $pdo->prepare($sqlDel);
        $stmtDel->execute($ids);
        $deleted += $stmtDel->rowCount();
        $log("  Deleted batch of " . count($ids) . ", total deleted={$deleted}");

        // Small pause to reduce lock pressure in very large tables
        usleep(100000); // 100ms
    }

    echo "Done. Deleted {$deleted} rows.\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
    fwrite(STDERR, "File: " . $e->getFile() . ":" . $e->getLine() . "\n");
    exit(1);
}


