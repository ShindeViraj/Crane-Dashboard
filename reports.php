<?php
/**
 * Reports — Historical data by date, all drives or individual drive
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Reports';
$pdo = getDbConnection();

// Get all cranes for dropdown
$cranes = $pdo->query("SELECT crane_id, name FROM cranes ORDER BY crane_id")->fetchAll();

// Report parameters
$craneId = $_GET['crane_id'] ?? ($_POST['crane_id'] ?? '');
$fromDate = $_GET['from'] ?? ($_POST['from'] ?? date('Y-m-d', strtotime('-7 days')));
$toDate = $_GET['to'] ?? ($_POST['to'] ?? date('Y-m-d'));
$driveFilter = $_GET['drive'] ?? ($_POST['drive'] ?? 'MH'); // Default to MH, avoids 40-col break
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build query based on filters
$reportData = [];
$totalRecords = 0;

if ($craneId) {
    // Select columns based on drive filter
    if ($driveFilter === 'all') {
        $selectCols = "Timestamp, crane_id,
            MH_Drive_status, MH_Output_frequency, MH_Motor_current, MH_Motor_torque, MH_Mains_voltage, MH_Motor_voltage, MH_Motor_power, MH_Drive_temp, MH_Motion_run_time, MH_Altivar_fault_code,
            CT_Drive_status, CT_Output_frequency, CT_Motor_current, CT_Motor_torque, CT_Mains_voltage, CT_Motor_voltage, CT_Motor_power, CT_Drive_temp, CT_Motion_run_time, CT_Altivar_fault_code,
            LT_Drive_status, LT_Output_frequency, LT_Motor_current, LT_Motor_torque, LT_Mains_voltage, LT_Motor_voltage, LT_Motor_power, LT_Drive_temp, LT_Motion_run_time, LT_Altivar_fault_code,
            AH_Drive_status, AH_Output_frequency, AH_Motor_current, AH_Motor_torque, AH_Mains_voltage, AH_Motor_voltage, AH_Motor_power, AH_Drive_temp, AH_Motion_run_time, AH_Altivar_fault_code";
    } else {
        $d = $driveFilter;
        $selectCols = "Timestamp, crane_id,
            {$d}_Drive_status as Drive_status, {$d}_Output_frequency as Output_frequency, {$d}_Motor_current as Motor_current, {$d}_Motor_torque as Motor_torque,
            {$d}_Mains_voltage as Mains_voltage, {$d}_Motor_voltage as Motor_voltage, {$d}_Motor_power as Motor_power, {$d}_Drive_temp as Drive_temp,
            {$d}_Motion_run_time as Motion_run_time, {$d}_Logic_input as Logic_input, {$d}_Logic_output as Logic_output, {$d}_Altivar_fault_code as Fault_code,
            {$d}_Encoder as Encoder, {$d}_Load_data as Load_data, {$d}_di as Digital_inputs";
    }
    
    // Count total records for this query
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM crane_data WHERE crane_id = :cid AND Timestamp >= :from AND Timestamp <= :to");
    $countStmt->execute([':cid' => $craneId, ':from' => $fromDate . ' 00:00:00', ':to' => $toDate . ' 23:59:59']);
    $totalRecords = $countStmt->fetchColumn();
    
    if ($exportCsv) {
        // Export CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_crane_' . $craneId . '_' . $driveFilter . '_' . $fromDate . '_to_' . $toDate . '.csv"');
        
        $stmt = $pdo->prepare("SELECT $selectCols FROM crane_data WHERE crane_id = :cid AND Timestamp >= :from AND Timestamp <= :to ORDER BY Timestamp ASC");
        $stmt->execute([':cid' => $craneId, ':from' => $fromDate . ' 00:00:00', ':to' => $toDate . ' 23:59:59']);
        
        $output = fopen('php://output', 'w');
        $first = true;
        while ($row = $stmt->fetch()) {
            if ($first) {
                fputcsv($output, array_keys($row));
                $first = false;
            }
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    
    // Fetch paginated data for display (limit 200 for browser)
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 200;
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare("SELECT $selectCols FROM crane_data WHERE crane_id = :cid AND Timestamp >= :from AND Timestamp <= :to ORDER BY Timestamp DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':cid', $craneId);
    $stmt->bindValue(':from', $fromDate . ' 00:00:00');
    $stmt->bindValue(':to', $toDate . ' 23:59:59');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reportData = $stmt->fetchAll();
    
    $totalPages = ceil($totalRecords / $perPage);
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item active">Reports</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title">Reports</h1>
</div>

<!-- Report Filters -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card">
            <h3 class="card-title text-uppercase"><i class="bi bi-funnel"></i> Report Filters</h3>
            <form method="GET" action="reports.php" id="report-form" class="report-filter-form">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="settings-label" for="filter-crane">Crane</label>
                        <select class="form-select form-input-custom" id="filter-crane" name="crane_id" required>
                            <option value="">Select Crane</option>
                            <?php foreach ($cranes as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['crane_id']); ?>" <?php echo $craneId === $c['crane_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['crane_id']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="settings-label" for="filter-drive">Drive</label>
                        <select class="form-select form-input-custom" id="filter-drive" name="drive">
                            <option value="all" <?php echo $driveFilter==='all'?'selected':''; ?>>All Drives</option>
                            <option value="MH" <?php echo $driveFilter==='MH'?'selected':''; ?>>Main Hoist (MH)</option>
                            <option value="CT" <?php echo $driveFilter==='CT'?'selected':''; ?>>Cross Travel (CT)</option>
                            <option value="LT" <?php echo $driveFilter==='LT'?'selected':''; ?>>Long Travel (LT)</option>
                            <option value="AH" <?php echo $driveFilter==='AH'?'selected':''; ?>>Aux Hoist (AH)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="settings-label" for="filter-from">From Date</label>
                        <input type="date" class="form-control form-input-custom" id="filter-from" name="from" value="<?php echo $fromDate; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="settings-label" for="filter-to">To Date</label>
                        <input type="date" class="form-control form-input-custom" id="filter-to" name="to" value="<?php echo $toDate; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary-gradient w-100" id="btn-generate-report">
                            <i class="bi bi-search"></i> Generate
                        </button>
                    </div>
                    <div class="col-md-2">
                        <?php if ($craneId && $totalRecords > 0): ?>
                        <a href="reports.php?crane_id=<?php echo urlencode($craneId); ?>&drive=<?php echo $driveFilter; ?>&from=<?php echo $fromDate; ?>&to=<?php echo $toDate; ?>&export=csv" 
                           class="btn btn-success-gradient w-100" id="btn-export-csv">
                            <i class="bi bi-download"></i> Export CSV
                        </a>
                        <?php else: ?>
                        <button class="btn btn-outline-action w-100" disabled>
                            <i class="bi bi-download"></i> Export CSV
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Results -->
<?php if ($craneId): ?>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card">
            <div class="card-header-bar">
                <div class="card-title-group">
                    <h3 class="card-title text-uppercase mb-0">
                        Report: <?php echo $driveFilter === 'all' ? 'All Drives' : $driveFilter . ' Drive'; ?>
                    </h3>
                    <span class="status-chip status-idle-chip">
                        <?php echo number_format($totalRecords); ?> records
                    </span>
                </div>
                <span class="text-muted" style="font-size:13px;">
                    <?php echo $fromDate; ?> to <?php echo $toDate; ?>
                </span>
            </div>
            
            <?php if (empty($reportData)): ?>
            <div class="text-center" style="padding:40px;">
                <i class="bi bi-journal-x" style="font-size:36px;color:#c4c6cf;"></i>
                <p style="color:#74777f;margin-top:12px;">No data found for the selected filters.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" style="max-height:600px;overflow-y:auto;">
                <table class="table table-custom table-sm" id="report-table">
                    <thead style="position:sticky;top:0;z-index:1;">
                        <tr>
                            <?php 
                            $headers = array_keys($reportData[0]);
                            foreach ($headers as $h): ?>
                            <th><?php echo str_replace('_', ' ', $h); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                        <tr>
                            <?php foreach ($row as $key => $val): ?>
                            <td <?php 
                                if (strpos($key,'fault_code') !== false && intval($val) > 0) echo 'class="fault-active"';
                                if (strpos($key,'Drive_temp') !== false && floatval($val) > 70) echo 'class="temp-danger"';
                            ?>><?php echo htmlspecialchars($val ?? '—'); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="report-pagination" style="margin-top:16px;">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?crane_id=<?php echo $craneId; ?>&drive=<?php echo $driveFilter; ?>&from=<?php echo $fromDate; ?>&to=<?php echo $toDate; ?>&page=<?php echo $page-1; ?>">
                            &laquo; Prev
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="page-item disabled">
                        <span class="page-link">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    </li>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?crane_id=<?php echo $craneId; ?>&drive=<?php echo $driveFilter; ?>&from=<?php echo $fromDate; ?>&to=<?php echo $toDate; ?>&page=<?php echo $page+1; ?>">
                            Next &raquo;
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
