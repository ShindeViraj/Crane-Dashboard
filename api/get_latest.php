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

// Strict crane_id validation: alphanumeric, dash, underscore (max 20 chars)
$craneId = isset($_GET['crane_id']) ? trim($_GET['crane_id']) : '1';
if (!preg_match('/^[a-zA-Z0-9_\-]{1,20}$/', $craneId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid crane_id parameter.']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT Timestamp, crane_id,
            MH_Drive_status, MH_Output_frequency, MH_Motor_current, MH_Motor_torque,
            MH_Mains_voltage, MH_Motor_voltage, MH_Motor_power, MH_Drive_temp,
            MH_Motion_run_time, MH_Logic_input, MH_Logic_output, MH_Altivar_fault_code,
            MH_Encoder, MH_Load_data, MH_di,
            CT_Drive_status, CT_Output_frequency, CT_Motor_current, CT_Motor_torque,
            CT_Mains_voltage, CT_Motor_voltage, CT_Motor_power, CT_Drive_temp,
            CT_Motion_run_time, CT_Logic_input, CT_Logic_output, CT_Altivar_fault_code,
            CT_Encoder, CT_Load_data, CT_di,
            LT_Drive_status, LT_Output_frequency, LT_Motor_current, LT_Motor_torque,
            LT_Mains_voltage, LT_Motor_voltage, LT_Motor_power, LT_Drive_temp,
            LT_Motion_run_time, LT_Logic_input, LT_Logic_output, LT_Altivar_fault_code,
            LT_Encoder, LT_Load_data, LT_di,
            AH_Drive_status, AH_Output_frequency, AH_Motor_current, AH_Motor_torque,
            AH_Mains_voltage, AH_Motor_voltage, AH_Motor_power, AH_Drive_temp,
            AH_Motion_run_time, AH_Logic_input, AH_Logic_output, AH_Altivar_fault_code,
            AH_Encoder, AH_Load_data, AH_di
        FROM crane_data WHERE crane_id = :crane_id ORDER BY Timestamp DESC LIMIT 1
    ");
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
    error_log('[BML-IOT] get_latest query failed: ' . $e->getMessage() . ' | crane_id=' . $craneId . ' | ' . date('c'));
    http_response_code(500);
    echo json_encode(['error' => 'Database error. Could not retrieve latest data.']);
}
?>