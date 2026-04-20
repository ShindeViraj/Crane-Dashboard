<?php
/**
 * API Endpoint: Get Latest Data
 * 
 * Returns the most recent crane_data record for a given crane_id.
 * Used by the dashboard for AJAX live data polling.
 * 
 * GET ?crane_id=1
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

require_once __DIR__ . '/../db/config.php';

$craneId = isset($_GET['crane_id']) ? htmlspecialchars(trim($_GET['crane_id'])) : '1';

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT * FROM crane_data WHERE crane_id = :crane_id ORDER BY Timestamp DESC LIMIT 1");
    $stmt->execute([':crane_id' => $craneId]);
    $row = $stmt->fetch();

    if ($row) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'No data found for crane_id ' . $craneId
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>