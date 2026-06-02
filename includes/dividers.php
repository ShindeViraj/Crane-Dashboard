<?php
/**
 * Dividers Helper — Load & cache parameter dividers for each crane.
 * 
 * Dividers are stored as JSON in the `cranes.dividers` column.
 * To avoid hitting the database on every incoming data request,
 * dividers are cached as local JSON files in the cache/ directory.
 * 
 * Format: { "MH_Output_frequency": 10, "CT_Motor_current": 100, ... }
 * Any parameter not listed defaults to 1 (no division).
 */

define('DIVIDERS_CACHE_DIR', __DIR__ . '/../cache');

// Parameters that support dividers
// Excludes: Altivar_fault_code
define('DIVISIBLE_PARAMS', [
    'Drive_status',
    'Output_frequency',
    'Motor_current',
    'Motor_torque',
    'Mains_voltage',
    'Motor_voltage',
    'Motor_power',
    'Drive_temp',
    'Motion_run_time',
    'Logic_input',
    'Logic_output',
    'Encoder',
    'Load_data',
    'di'
]);

define('MOTION_PREFIXES', ['MH', 'CT', 'LT', 'AH']);

/**
 * Get the cache file path for a crane's dividers.
 */
function getDividersCacheFile($craneId) {
    return DIVIDERS_CACHE_DIR . '/dividers_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $craneId) . '.json';
}

/**
 * Save dividers to both the database and the local cache file.
 * Call this whenever dividers are updated via the manage_cranes UI.
 *
 * @param PDO $pdo Database connection
 * @param string $craneId The crane identifier
 * @param array $dividers Associative array of param => divider value
 */
function saveDividers($pdo, $craneId, $dividers) {
    // Clean: remove entries that are empty or equal to 1 (default)
    $cleaned = [];
    foreach ($dividers as $key => $val) {
        $val = floatval($val);
        if ($val != 0 && $val != 1) {
            $cleaned[$key] = $val;
        }
    }
    
    $json = !empty($cleaned) ? json_encode($cleaned) : null;
    
    // Save to database
    $stmt = $pdo->prepare("UPDATE cranes SET dividers = :div WHERE crane_id = :cid");
    $stmt->execute([':div' => $json, ':cid' => $craneId]);
    
    // Sync to cache file
    syncDividersCache($craneId, $cleaned);
}

/**
 * Write dividers to the local cache file (no DB needed).
 */
function syncDividersCache($craneId, $dividers) {
    if (!is_dir(DIVIDERS_CACHE_DIR)) {
        @mkdir(DIVIDERS_CACHE_DIR, 0755, true);
    }
    $file = getDividersCacheFile($craneId);
    file_put_contents($file, json_encode($dividers ?: []), LOCK_EX);
}

/**
 * Load dividers from the local cache file.
 * Returns an associative array of param => divider value.
 * Falls back to empty array (all dividers = 1) if no cache exists.
 */
function loadDividers($craneId) {
    $file = getDividersCacheFile($craneId);
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

/**
 * Apply dividers to a single data record (associative array).
 * Modifies the record in-place and returns it.
 *
 * @param array &$record The data record to scale
 * @param array $dividers The dividers map
 * @return array The modified record
 */
function applyDividers(&$record, $dividers) {
    if (empty($dividers)) return $record;
    
    foreach ($dividers as $param => $divisor) {
        if ($divisor == 0 || $divisor == 1) continue;
        if (isset($record[$param]) && is_numeric($record[$param])) {
            $record[$param] = round(floatval($record[$param]) / $divisor, 4);
        }
    }
    return $record;
}

/**
 * Parse dividers from a form POST submission.
 * Form fields are named like: divider[MH_Output_frequency], divider[CT_Motor_current], etc.
 *
 * @param array $postData The $_POST array
 * @return array Cleaned dividers array
 */
function parseDividersFromForm($postData) {
    $dividers = [];
    $formDividers = $postData['divider'] ?? [];
    
    if (is_array($formDividers)) {
        foreach ($formDividers as $key => $val) {
            $val = trim($val);
            if ($val !== '' && is_numeric($val) && floatval($val) != 0) {
                $dividers[$key] = floatval($val);
            }
        }
    }
    return $dividers;
}
?>
