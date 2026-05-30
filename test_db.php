<?php
// Enable basic error reporting for the test script
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database configuration (which safely loads credentials from .env)
require_once __DIR__ . '/db/config.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Attempt to get the database connection
    $pdo = getDbConnection();
    
    echo "<p style='color: green;'><strong>SUCCESS:</strong> Connected to the database successfully!</p>";
    
    // Test a basic query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "<p><strong>MySQL Version:</strong> " . htmlspecialchars($result['version']) . "</p>";

} catch (Exception $e) {
    // If it fails, db/config.php's getDbConnection() might catch it first and die,
    // but if it throws an exception up to here, we catch it safely.
    echo "<p style='color: red;'><strong>FAILED:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
