<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// We include config.php just to load the .env values
require_once __DIR__ . '/db/config.php';

echo "<h2>Database Connection Test & Diagnostic Tool</h2>";

try {
    // DO NOT use getDbConnection() because it has a die() statement that hides the error.
    // Instead, we connect manually using the constants loaded from .env
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    echo "<p style='color: green;'><strong>SUCCESS:</strong> Connected to the database successfully!</p>";
    
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "<p><strong>MySQL Version:</strong> " . htmlspecialchars($result['version']) . "</p>";

} catch (PDOException $e) {
    echo "<div style='background-color: #ffebee; padding: 20px; border: 1px solid #f44336; border-radius: 5px;'>";
    echo "<h3 style='color: #d32f2f; margin-top: 0;'>CONNECTION FAILED</h3>";
    echo "<p><strong>Error Message:</strong> <span style='font-family: monospace;'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
    echo "<p><strong>Error Code:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
    
    if (strpos($e->getMessage(), '1226') !== false || strpos($e->getMessage(), 'max_connections_per_hour') !== false) {
        echo "<p style='color: #d32f2f;'><strong>DIAGNOSIS:</strong> You are still blocked by Hostinger's hourly limit. The limit resets exactly one hour after you first hit it. You just have to wait.</p>";
    } elseif (strpos($e->getMessage(), '1045') !== false || strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p style='color: #d32f2f;'><strong>DIAGNOSIS:</strong> Your .env credentials (username or password) are incorrect.</p>";
    } elseif (strpos($e->getMessage(), '2002') !== false || strpos($e->getMessage(), 'Unknown MySQL server') !== false) {
        echo "<p style='color: #d32f2f;'><strong>DIAGNOSIS:</strong> The DB_HOST in your .env file is wrong or unreachable.</p>";
    }
    echo "</div>";
}
?>
