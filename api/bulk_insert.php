<?php
/**
 * API Endpoint: Bulk Insert from Raspberry Pi
 * 
 * When the Node-RED gateway loses internet, data is stored locally on the
 * Raspberry Pi's SQL database. When internet reconnects, the Pi sends
 * the entire local table (SELECT * result) as a JSON array to this endpoint.
 * 
 * Accepts POST with JSON array of records.
 * Uses multi-row batch INSERT for speed.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// ── Payload Size Limit (50 MB max for bulk) ──────────────────────
// Check Content-Length header FIRST to reject oversized payloads immediately
// without reading the entire body into memory.
$maxPayloadBytes = 50 * 1024 * 1024; // 50 MB
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
if ($contentLength > $maxPayloadBytes) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large. Maximum 50 MB for bulk insert.']);
    exit;
}

// Allow up to 5 minutes for large imports
set_time_limit(300);

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../includes/dividers.php';

// ── Per-IP Rate Limiting (10 req/min for bulk) ──────────────────
$rateLimitDir = sys_get_temp_dir() . '/bml_ratelimit';
if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0755, true);
}
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $rateLimitDir . '/' . md5($clientIp . '_bulk') . '.json';
$rateWindow = 60;
$rateMax = 10;

$rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : null;
if (!$rateData || (time() - ($rateData['window_start'] ?? 0)) > $rateWindow) {
    $rateData = ['window_start' => time(), 'count' => 0];
}
$rateData['count']++;
file_put_contents($rateFile, json_encode($rateData), LOCK_EX);

if ($rateData['count'] > $rateMax) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Max ' . $rateMax . ' bulk requests per minute.']);
    exit;
}

// Now read the body (we already know it's within size limits)
$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > $maxPayloadBytes) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large. Maximum 50 MB for bulk insert.']);
    exit;
}

$records = json_decode($rawInput, true);
unset($rawInput); // Free memory immediately

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

// Ensure it's an array of records
if (!is_array($records) || empty($records)) {
    http_response_code(400);
    echo json_encode(['error' => 'Expected a non-empty JSON array of records.']);
    exit;
}

// If a single object was sent, wrap it
if (isset($records['Timestamp']) || isset($records['id'])) {
    $records = [$records];
}

// Max record cap (200000 per batch)
if (count($records) > 200000) {
    http_response_code(400);
    echo json_encode(['error' => 'Too many records. Maximum 200,000 per batch.']);
    exit;
}

// Define all valid columns (excluding 'id' since the cloud DB has its own auto-increment)
$validColumns = [
    'Timestamp',
    'crane_id',
    'MH_Drive_status',
    'MH_Output_frequency',
    'MH_Motor_current',
    'MH_Motor_torque',
    'MH_Mains_voltage',
    'MH_Motor_voltage',
    'MH_Motor_power',
    'MH_Drive_temp',
    'MH_Motion_run_time',
    'MH_Logic_input',
    'MH_Logic_output',
    'MH_Altivar_fault_code',
    'MH_Encoder',
    'MH_Load_data',
    'MH_di',
    'CT_Drive_status',
    'CT_Output_frequency',
    'CT_Motor_current',
    'CT_Motor_torque',
    'CT_Mains_voltage',
    'CT_Motor_voltage',
    'CT_Motor_power',
    'CT_Drive_temp',
    'CT_Motion_run_time',
    'CT_Logic_input',
    'CT_Logic_output',
    'CT_Altivar_fault_code',
    'CT_Encoder',
    'CT_Load_data',
    'CT_di',
    'LT_Drive_status',
    'LT_Output_frequency',
    'LT_Motor_current',
    'LT_Motor_torque',
    'LT_Mains_voltage',
    'LT_Motor_voltage',
    'LT_Motor_power',
    'LT_Drive_temp',
    'LT_Motion_run_time',
    'LT_Logic_input',
    'LT_Logic_output',
    'LT_Altivar_fault_code',
    'LT_Encoder',
    'LT_Load_data',
    'LT_di',
    'AH_Drive_status',
    'AH_Output_frequency',
    'AH_Motor_current',
    'AH_Motor_torque',
    'AH_Mains_voltage',
    'AH_Motor_voltage',
    'AH_Motor_power',
    'AH_Drive_temp',
    'AH_Motion_run_time',
    'AH_Logic_input',
    'AH_Logic_output',
    'AH_Altivar_fault_code',
    'AH_Encoder',
    'AH_Load_data',
    'AH_di'
];

$columnCount = count($validColumns);
$columnList = implode(', ', $validColumns);

// ── Pre-process all records: fix timestamps ───────────────────────
foreach ($records as &$record) {
    if (isset($record['Timestamp'])) {
        $ts = $record['Timestamp'];
        if (strpos($ts, 'T') !== false) {
            try {
                $dt = new DateTime($ts);
                $record['Timestamp'] = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Leave as-is, DB will handle or reject
            }
        }
    }
}
unset($record); // Break reference

// ── Pre-process: Apply parameter dividers (from local cache, 0 DB hits)
$bulkCraneId = isset($records[0]['crane_id']) ? $records[0]['crane_id'] : '1';
$bulkDividers = loadDividers($bulkCraneId);
if (!empty($bulkDividers)) {
    foreach ($records as &$record) {
        applyDividers($record, $bulkDividers);
    }
    unset($record);
}

// ── Multi-row batch INSERT (500 rows per query) ──────────────────
// Instead of inserting one row at a time, we build multi-row INSERT
// statements like: INSERT INTO ... VALUES (...), (...), (...) ...
// This is 10-50x faster than single-row inserts.
$BATCH_SIZE = 500;
$totalRecords = count($records);
$insertedCount = 0;
$errorCount = 0;

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    for ($offset = 0; $offset < $totalRecords; $offset += $BATCH_SIZE) {
        $batch = array_slice($records, $offset, $BATCH_SIZE);
        $batchCount = count($batch);

        // Build placeholders: (?, ?, ...), (?, ?, ...), ...
        $singleRow = '(' . implode(',', array_fill(0, $columnCount, '?')) . ')';
        $allRows = implode(',', array_fill(0, $batchCount, $singleRow));

        $sql = "INSERT INTO crane_data ({$columnList}) VALUES {$allRows}";
        $stmt = $pdo->prepare($sql);

        // Flatten all values into a single array
        $values = [];
        foreach ($batch as $record) {
            foreach ($validColumns as $col) {
                $values[] = isset($record[$col]) ? $record[$col] : null;
            }
        }

        $stmt->execute($values);
        $insertedCount += $batchCount;
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Bulk insert completed.',
        'total_received' => $totalRecords,
        'inserted' => $insertedCount,
        'errors' => $errorCount
    ]);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[BML-IOT] bulk_insert transaction failed: ' . $e->getMessage() . ' | ' . date('c'));
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error. Bulk insert failed.',
        'detail' => $e->getMessage()
    ]);
}
?>