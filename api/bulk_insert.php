<?php
/**
 * API Endpoint: Bulk Insert from Raspberry Pi
 * 
 * When the Node-RED gateway loses internet, data is stored locally on the
 * Raspberry Pi's SQL database. When internet reconnects, the Pi sends
 * the entire local table (SELECT * result) as a JSON array to this endpoint.
 * 
 * Accepts POST with JSON array of records.
 * Uses batch INSERT with duplicate handling.
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

require_once __DIR__ . '/../db/config.php';

// Read JSON input
$rawInput = file_get_contents('php://input');
$records = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
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

// Define all valid columns (excluding 'id' since the cloud DB has its own auto-increment)
$validColumns = [
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

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    
    $insertedCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Prepare the INSERT statement once
    // Use all valid columns for the prepared statement
    $placeholders = array_map(function($col) { return ':' . $col; }, $validColumns);
    $sql = "INSERT INTO crane_data (" . implode(', ', $validColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    
    foreach ($records as $index => $record) {
        try {
            // Handle Timestamp format from Raspberry Pi (ISO 8601 -> MySQL datetime)
            if (isset($record['Timestamp'])) {
                $ts = $record['Timestamp'];
                // Convert ISO 8601 format (2026-04-09T09:01:37.000Z) to MySQL format
                if (strpos($ts, 'T') !== false) {
                    $dt = new DateTime($ts);
                    $record['Timestamp'] = $dt->format('Y-m-d H:i:s');
                }
            }
            
            // Build parameter values, using NULL for missing columns
            $params = [];
            foreach ($validColumns as $col) {
                $params[':' . $col] = isset($record[$col]) ? $record[$col] : null;
            }
            
            $stmt->execute($params);
            $insertedCount++;
        } catch (PDOException $e) {
            $errorCount++;
            // Log per-record errors server-side only
            error_log('[BML-IOT] bulk_insert record ' . ($index + 1) . ' failed: ' . $e->getMessage() . ' | ' . date('c'));
        }
    }
    
    $pdo->commit();
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Bulk insert completed.",
        'total_received' => count($records),
        'inserted' => $insertedCount,
        'errors' => $errorCount
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[BML-IOT] bulk_insert transaction failed: ' . $e->getMessage() . ' | ' . date('c'));
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error. Bulk insert failed.'
    ]);
}
?>
