<?php
/**
 * tools/migrate.php
 * Production Database Migration Runner for FinCart
 * 
 * Usage:
 *   php tools/migrate.php          (Executes all pending migrations)
 *   php tools/migrate.php --status (Displays migration status)
 */

if (php_sapi_name() !== 'cli') {
    die("This tool can only be run from the command line.");
}

require_once __DIR__ . '/../db.php';

echo "=========================================\n";
echo " FinCart Database Migration Engine\n";
echo "=========================================\n\n";

// Ensure migrations table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS migrations (
      id INT AUTO_INCREMENT PRIMARY KEY,
      migration_name VARCHAR(191) NOT NULL UNIQUE,
      applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$migrations_dir = __DIR__ . '/../database/migrations';
if (!is_dir($migrations_dir)) {
    die("Error: Migrations directory not found at $migrations_dir\n");
}

$files = scandir($migrations_dir);
$migration_files = array_filter($files, function($f) {
    return pathinfo($f, PATHINFO_EXTENSION) === 'sql';
});
sort($migration_files);

// Fetch applied migrations
$applied = [];
$res = $conn->query("SELECT migration_name, applied_at FROM migrations ORDER BY id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $applied[$row['migration_name']] = $row['applied_at'];
    }
}

$is_status = in_array('--status', $argv, true);

if ($is_status) {
    echo sprintf("%-45s | %-20s\n", "Migration File", "Status");
    echo str_repeat("-", 70) . "\n";
    foreach ($migration_files as $mf) {
        $status = isset($applied[$mf]) ? "Applied on " . $applied[$mf] : "PENDING";
        echo sprintf("%-45s | %-20s\n", $mf, $status);
    }
    echo "\n";
    exit(0);
}

// Execute pending migrations
$pending_count = 0;
foreach ($migration_files as $mf) {
    if (isset($applied[$mf])) {
        continue;
    }

    $pending_count++;
    echo "[MIGRATING] $mf ... ";

    $sql = file_get_contents($migrations_dir . '/' . $mf);
    if ($sql === false || trim($sql) === '') {
        echo "FAILED (Empty file)\n";
        continue;
    }

    // Split multiple queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));

    $conn->begin_transaction();
    try {
        foreach ($queries as $query) {
            // Strip leading comment lines
            $clean_query = preg_replace('/^--.*$/m', '', $query);
            $clean_query = trim($clean_query);
            if (!empty($clean_query)) {
                if (!$conn->query($clean_query)) {
                    throw new Exception($conn->error . " in query:\n" . substr($clean_query, 0, 100));
                }
            }
        }

        // Record successful migration
        $rec = $conn->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        $rec->bind_param("s", $mf);
        $rec->execute();

        $conn->commit();
        echo "SUCCESS\n";
    } catch (Exception $e) {
        $conn->rollback();
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($pending_count === 0) {
    echo "Database is already up to date. No pending migrations.\n";
} else {
    echo "\nSuccessfully applied $pending_count migration(s).\n";
}

