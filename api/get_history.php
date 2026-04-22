<?php
/**
 * API Endpoint: Get Historical Data
 * 
 * Returns historical crane_data records for charts and reports.
 * 
 * GET ?crane_id=1&from=2026-04-01&to=2026-04-16&limit=100
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

require_once __DIR__ . '/../db/config.php';

// Strict parameter validation
$craneId = isset($_GET['crane_id']) ? trim($_GET['crane_id']) : '1';
if (!preg_match('/^[a-zA-Z0-9_\-]{1,20}$/', $craneId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid crane_id parameter.']);
    exit;
}

$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d H:i:s');
$limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 1000) : 500;

// Strict date format validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $from) || !strtotime($from)) {
    $from = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $to) || !strtotime($to)) {
    $to = date('Y-m-d H:i:s');
}

try {
    $pdo = getDbConnection();

    // Get aggregated daily power data for charts
    $stmt = $pdo->prepare("
        SELECT 
            DATE(Timestamp) as date,
            AVG(MH_Motor_power) as avg_mh_power,
            AVG(CT_Motor_power) as avg_ct_power,
            AVG(LT_Motor_power) as avg_lt_power,
            AVG(AH_Motor_power) as avg_ah_power,
            AVG(MH_Motor_power + CT_Motor_power + LT_Motor_power + AH_Motor_power) as avg_total_power,
            MAX(MH_Motor_power + CT_Motor_power + LT_Motor_power + AH_Motor_power) as max_total_power,
            COUNT(*) as sample_count
        FROM crane_data 
        WHERE crane_id = :crane_id 
          AND Timestamp >= :from_date 
          AND Timestamp <= :to_date
        GROUP BY DATE(Timestamp)
        ORDER BY DATE(Timestamp) ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':crane_id', $craneId, PDO::PARAM_INT);
    $stmt->bindValue(':from_date', $from);
    $stmt->bindValue(':to_date', $to);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'crane_id' => $craneId,
        'from' => $from,
        'to' => $to,
        'data' => $rows
    ]);
} catch (PDOException $e) {
    error_log('[BML-IOT] get_history query failed: ' . $e->getMessage() . ' | crane_id=' . $craneId . ' | ' . date('c'));
    http_response_code(500);
    echo json_encode(['error' => 'Database error. Could not retrieve history.']);
}
?>