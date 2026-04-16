<?php
/**
 * API Endpoint: Receive Data from Node-RED
 * 
 * Accepts POST/PUT with JSON body containing VFD parameters.
 * Inserts a single record into crane_data table.
 * 
 * Node-RED HTTP Request node should point to this URL.
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

// Read JSON input
$rawInput = file_get_contents('php://input');

// Aggressive sanitization: Remove any raw ASCII control characters (0x00-0x1F) from the raw string
// VFD/Modbus drivers often append trailing null bytes or raw hex dumps that crash JSON parsers.
$cleanInput = preg_replace('/[\x00-\x1F\x7F]/', '', $rawInput);

$data = json_decode($cleanInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid JSON: ' . json_last_error_msg(),
        'debug_escaped_input' => substr($cleanInput, 0, 200),
        'debug_hex_input' => bin2hex(substr($rawInput, 0, 50))
    ]);
    exit;
}

// Define expected columns
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

// Build insert data — only include columns that exist in the payload
$insertCols = [];
$insertPlaceholders = [];
$insertValues = [];

foreach ($columns as $col) {
    if (array_key_exists($col, $data)) {
        $insertCols[] = $col;
        $insertPlaceholders[] = ':' . $col;
        $insertValues[':' . $col] = $data[$col];
    }
}

if (empty($insertCols)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid columns found in payload.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $sql = "INSERT INTO crane_data (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insertValues);
    
    $insertId = $pdo->lastInsertId();
    
    // Update crane status to online
    $craneIdVal = isset($data['crane_id']) ? $data['crane_id'] : '1';
    $tsVal = isset($data['Timestamp']) ? $data['Timestamp'] : date('Y-m-d H:i:s');
    $updateStmt = $pdo->prepare("UPDATE cranes SET status = 'online', last_data_at = :ts WHERE crane_id = :cid");
    $updateStmt->execute([':ts' => $tsVal, ':cid' => $craneIdVal]);
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Data inserted successfully.',
        'id' => $insertId
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
