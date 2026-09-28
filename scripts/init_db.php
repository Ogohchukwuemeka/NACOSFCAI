<?php
require_once dirname(__DIR__) . '/config/database.php';
$pdo = Database::getInstance()->getConnection();

// Load canonical SQL from database.sql and execute statements.
// This keeps all schema/seed SQL in one place.
$sqlFile = dirname(__DIR__) . '/database.sql';
if (!file_exists($sqlFile)) {
    echo "Error: database.sql not found at $sqlFile";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    echo "Error: unable to read database.sql";
    exit(1);
}

try {
    // Split statements by semicolon followed by newline to avoid splitting inside definitions.
    $parts = preg_split('/;\r?\n/', $sql);
    $executed = 0;
    foreach ($parts as $part) {
        $stmt = trim($part);
        if ($stmt === '') continue;
        // PDO::exec can run single statements; skip statements that are comments or SET commands.
        if (preg_match('/^--|^SET |^USE /i', $stmt)) continue;
        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (Throwable $e) {
            // Continue on errors (e.g., duplicate index/table) but report.
            echo "Warning executing statement: " . $e->getMessage() . "\n";
        }
    }
    echo "Executed approx. $executed statements from database.sql\n";
} catch (Throwable $e) {
    echo "Error processing database.sql: " . $e->getMessage() . "\n";
    exit(1);
}
