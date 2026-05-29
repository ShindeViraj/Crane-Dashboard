<?php
/**
 * API Endpoint: Receive Data from Node-RED
 * 
 * Accepts POST/PUT with JSON body containing VFD parameters.
 * Uses a hybrid file-buffered ingestion pipeline:
 *   1. Writes live state to cache/live_state_<crane_id>.json (instant, no DB)
 *   2. Downsamples: buffers every 2nd entry into cache/buffer_<crane_id>.json
 *   3. When buffer reaches 10 entries, flushes all to MySQL in a single transaction
 * 
 * This reduces database connections by >90%, keeping within hosting limits.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST or PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST or PUT.']);
    exit;
}

require_once __DIR__ . '/../db/config.php';

// ── Configurable Buffer Settings ──────────────────────────────────
// DOWNSAMPLE_RATE: Save every N-th entry to the buffer (2 = every 2nd second)
define('DOWNSAMPLE_RATE', 2);
// BUFFER_FLUSH_SIZE: Number of buffered entries before flushing to MySQL
define('BUFFER_FLUSH_SIZE', 10);
// CACHE_DIR: Directory for runtime cache files
define('CACHE_DIR', __DIR__ . '/../cache');

// ── Phase 5: Payload Size Limit (32 KB max for single records) ────
$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 32768) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large. Maximum 32 KB.']);
    exit;
}

// ── Phase 5: Per-IP Rate Limiting (60 req/min) ───────────────────
$rateLimitDir = sys_get_temp_dir() . '/bml_ratelimit';
if (!is_dir($rateLimitDir)) { @mkdir($rateLimitDir, 0755, true); }
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = $rateLimitDir . '/' . md5($clientIp . '_receive') . '.json';
$rateWindow = 60; // seconds
$rateMax = 60;     // max requests per window

$rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : null;
if (!$rateData || (time() - ($rateData['window_start'] ?? 0)) > $rateWindow) {
    $rateData = ['window_start' => time(), 'count' => 0];
}
$rateData['count']++;
file_put_contents($rateFile, json_encode($rateData), LOCK_EX);

if ($rateData['count'] > $rateMax) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Max ' . $rateMax . ' requests per minute.']);
    exit;
}

// Aggressive sanitization: Remove any raw ASCII control characters (0x00-0x1F)
// VFD/Modbus drivers often append trailing null bytes or raw hex dumps that crash JSON parsers.
$cleanInput = preg_replace('/[\x00-\x1F\x7F]/', '', $rawInput);

$data = json_decode($cleanInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

// ── Phase 5: Schema Validation ────────────────────────────────────
// Define expected columns (allowlist)
$columns = [
    'Timestamp', 'crane_id',
    'MH_Drive_status', 'MH_Output_frequency', 'MH_Motor_current', 'MH_Motor_torque',
    'MH_Mains_voltage', 'MH_Motor_voltage', 'MH_Motor_power', 'MH_Drive_temp',
    'MH_Motion_run_time', 'MH_Logic_input', 'MH_Logic_output', 'MH_Altivar_fault_code',
    'MH_Encoder', 'MH_Load_data', 'MH_di',
    'CT_Drive_status', 'CT_Output_frequency', 'CT_Motor_current', 'CT_Motor_torque',
    'CT_Mains_voltage', 'CT_Motor_voltage', 'CT_Motor_power', 'CT_Drive_temp',
    'CT_Motion_run_time', 'CT_Logic_input', 'CT_Logic_output', 'CT_Altivar_fault_code',
    'CT_Encoder', 'CT_Load_data', 'CT_di',
    'LT_Drive_status', 'LT_Output_frequency', 'LT_Motor_current', 'LT_Motor_torque',
    'LT_Mains_voltage', 'LT_Motor_voltage', 'LT_Motor_power', 'LT_Drive_temp',
    'LT_Motion_run_time', 'LT_Logic_input', 'LT_Logic_output', 'LT_Altivar_fault_code',
    'LT_Encoder', 'LT_Load_data', 'LT_di',
    'AH_Drive_status', 'AH_Output_frequency', 'AH_Motor_current', 'AH_Motor_torque',
    'AH_Mains_voltage', 'AH_Motor_voltage', 'AH_Motor_power', 'AH_Drive_temp',
    'AH_Motion_run_time', 'AH_Logic_input', 'AH_Logic_output', 'AH_Altivar_fault_code',
    'AH_Encoder', 'AH_Load_data', 'AH_di'
];

// Reject unknown keys
$unknownKeys = array_diff(array_keys($data), $columns);
if (!empty($unknownKeys)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown fields in payload: ' . implode(', ', array_slice($unknownKeys, 0, 5))]);
    exit;
}

// crane_id validation
if (isset($data['crane_id']) && !preg_match('/^[a-zA-Z0-9_\-]{1,20}$/', (string)$data['crane_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid crane_id value.']);
    exit;
}

// Numeric range enforcement for VFD parameters
foreach ($data as $key => $val) {
    if ($key === 'Timestamp' || $key === 'crane_id') continue;
    if ($val !== null && $val !== '' && !is_numeric($val)) {
        // Allow string-encoded numbers from VFD drivers
        if (!is_numeric(str_replace([' ', ','], '', (string)$val))) {
            http_response_code(400);
            echo json_encode(['error' => "Non-numeric value for field '$key'."]);
            exit;
        }
    }
    // Range: VFD values should be within reasonable industrial bounds
    if (is_numeric($val) && (abs((float)$val) > 100000)) {
        http_response_code(400);
        echo json_encode(['error' => "Value out of range for field '$key'."]);
        exit;
    }
}

// ── Phase 5: Anti-Replay — reject timestamps > 24h old or far in the future ──
// Node-RED sends timestamps in IST (Asia/Kolkata) WITHOUT a timezone suffix,
// e.g. "2026-05-29 11:56:00". The Hostinger server's time() returns UTC epoch.
// If we naively strtotime() that string, PHP treats it as UTC — placing it
// 5.5 hours in the future relative to the real UTC time. Fix: parse as IST.
if (isset($data['Timestamp'])) {
    try {
        $ist = new DateTimeZone('Asia/Kolkata');
        $utc = new DateTimeZone('UTC');
        // Parse the timestamp assuming it was generated in IST
        $dt = new DateTime($data['Timestamp'], $ist);
        $ts = $dt->getTimestamp(); // now in UTC epoch seconds
    } catch (Exception $e) {
        $ts = false;
    }
    if ($ts !== false) {
        $now = time(); // UTC epoch on server
        if ($ts < ($now - 86400)) {
            http_response_code(400);
            echo json_encode(['error' => 'Timestamp too old. Data must be less than 24 hours old.']);
            exit;
        }
        if ($ts > ($now + 600)) { // 10 min future tolerance
            http_response_code(400);
            echo json_encode(['error' => 'Timestamp is in the future. Server UTC: ' . gmdate('Y-m-d H:i:s', $now) . ', parsed UTC: ' . gmdate('Y-m-d H:i:s', $ts)]);
            exit;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// HYBRID FILE-BUFFERED INGESTION PIPELINE
// ═══════════════════════════════════════════════════════════════════

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

$craneIdVal = isset($data['crane_id']) ? $data['crane_id'] : '1';
$tsVal = isset($data['Timestamp']) ? $data['Timestamp'] : date('Y-m-d H:i:s');

// ── Step 1: Write Live State (every request, no DB) ──────────────
// This file is read by get_latest.php for real-time dashboard display.
$liveStateFile = CACHE_DIR . '/live_state_' . $craneIdVal . '.json';
$livePayload = [
    '_cached_at' => time(),
    '_timestamp_ist' => $tsVal,
    'data' => $data
];
file_put_contents($liveStateFile, json_encode($livePayload), LOCK_EX);

// ── Step 2: Downsample Counter ───────────────────────────────────
// Only buffer every N-th entry for database storage.
$counterFile = CACHE_DIR . '/counter_' . $craneIdVal . '.txt';
$counter = file_exists($counterFile) ? (int)file_get_contents($counterFile) : 0;
$counter++;
file_put_contents($counterFile, (string)$counter, LOCK_EX);

$shouldBuffer = ($counter % DOWNSAMPLE_RATE === 0);

if (!$shouldBuffer) {
    // Skip database buffering for this entry — live state is already updated
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Live state updated. Entry skipped by downsampler (sample ' . $counter . ').',
        'buffered' => false
    ]);
    exit;
}

// ── Step 3: Append to Buffer File ────────────────────────────────
$bufferFile = CACHE_DIR . '/buffer_' . $craneIdVal . '.json';

// Read existing buffer (with file lock for concurrency safety)
$bufferHandle = fopen($bufferFile, 'c+');
if (!$bufferHandle) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot open buffer file.']);
    exit;
}
flock($bufferHandle, LOCK_EX);

$bufferRaw = '';
while (!feof($bufferHandle)) {
    $bufferRaw .= fread($bufferHandle, 8192);
}
$buffer = !empty($bufferRaw) ? json_decode($bufferRaw, true) : [];
if (!is_array($buffer)) {
    $buffer = [];
}

// Build the row to buffer (only columns present in payload)
$row = [];
foreach ($columns as $col) {
    if (array_key_exists($col, $data)) {
        $row[$col] = $data[$col];
    }
}
$buffer[] = $row;

// ── Step 4: Check if Buffer is Full → Flush to MySQL ─────────────
if (count($buffer) >= BUFFER_FLUSH_SIZE) {
    // Flush all buffered entries to MySQL in a single transaction
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        foreach ($buffer as $entry) {
            $insertCols = [];
            $insertPlaceholders = [];
            $insertValues = [];
            foreach ($columns as $col) {
                if (array_key_exists($col, $entry)) {
                    $insertCols[] = $col;
                    $insertPlaceholders[] = ':' . $col;
                    $insertValues[':' . $col] = $entry[$col];
                }
            }
            if (!empty($insertCols)) {
                $sql = "INSERT INTO crane_data (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($insertValues);
            }
        }

        // Update crane status to online (using the latest entry's timestamp)
        $updateStmt = $pdo->prepare("UPDATE cranes SET status = 'online', last_data_at = :ts WHERE crane_id = :cid");
        $updateStmt->execute([':ts' => $tsVal, ':cid' => $craneIdVal]);

        $pdo->commit();

        // Clear the buffer file
        ftruncate($bufferHandle, 0);
        rewind($bufferHandle);
        flock($bufferHandle, LOCK_UN);
        fclose($bufferHandle);

        // Reset counter
        file_put_contents($counterFile, '0', LOCK_EX);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Buffer flushed. ' . count($buffer) . ' records inserted into database.',
            'buffered' => false,
            'flushed' => count($buffer)
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[BML-IOT] Buffer flush failed: ' . $e->getMessage() . ' | crane_id=' . $craneIdVal . ' | ' . date('c'));

        // Keep the buffer intact so we can retry on the next request
        ftruncate($bufferHandle, 0);
        rewind($bufferHandle);
        fwrite($bufferHandle, json_encode($buffer));
        flock($bufferHandle, LOCK_UN);
        fclose($bufferHandle);

        http_response_code(500);
        echo json_encode(['error' => 'Database flush failed. Buffer preserved for retry.']);
    }
} else {
    // Buffer is not full yet — save updated buffer and return without DB connection
    ftruncate($bufferHandle, 0);
    rewind($bufferHandle);
    fwrite($bufferHandle, json_encode($buffer));
    flock($bufferHandle, LOCK_UN);
    fclose($bufferHandle);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Entry buffered (' . count($buffer) . '/' . BUFFER_FLUSH_SIZE . '). No DB connection used.',
        'buffered' => true,
        'buffer_count' => count($buffer)
    ]);
}
?>
