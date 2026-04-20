<?php
/**
 * Fault History Reports
 * Standalone module to fetch, deduplicate, and display explicitly the Fault Codes
 */
require_once 'includes/auth.php';
require_once 'includes/fault_codes.php';
requireLogin();

$pageTitle = 'Fault History';

$craneId = $_GET['crane_id'] ?? '';
$driveFilter = $_GET['drive'] ?? 'all'; 

// Default to last 7 days to prevent massive payloads
$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');
$export = $_GET['export'] ?? '';

// Fetch all cranes for the dropdown
$stmt = $pdo->query("SELECT crane_id, name FROM cranes ORDER BY name ASC");
$cranes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$faultEvents = [];
$totalEvents = 0;

if ($craneId) {
    // We only need Timestamp and the Fault Code columns for the deduplication logic
    $columns = ['Timestamp'];
    if ($driveFilter === 'all') {
        $columns = array_merge($columns, ['MH_Altivar_fault_code', 'CT_Altivar_fault_code', 'LT_Altivar_fault_code', 'AH_Altivar_fault_code']);
    } else {
        $columns[] = strtoupper($driveFilter) . "_Altivar_fault_code";
    }
    
    $selectString = implode(', ', $columns);
    
    // Query chronological order for proper deduplication
    $stmt = $pdo->prepare("SELECT $selectString FROM crane_data WHERE crane_id = :cid AND Timestamp >= :from AND Timestamp <= :to ORDER BY Timestamp ASC");
    $stmt->execute([
        ':cid'  => $craneId,
        ':from' => $fromDate . ' 00:00:00',
        ':to'   => $toDate . ' 23:59:59'
    ]);
    
    // Deduplication Engine
    $lastFaults = [
        'MH' => 0,
        'CT' => 0,
        'LT' => 0,
        'AH' => 0
    ];
    
    $drivesToScan = ($driveFilter === 'all') ? ['MH', 'CT', 'LT', 'AH'] : [strtoupper($driveFilter)];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach ($drivesToScan as $d) {
            $colName = $d . '_Altivar_fault_code';
            $fc = intval($row[$colName] ?? 0);
            
            // If there's an active fault (fc > 0), and it is DIFFERENT from the last recorded state
            if ($fc > 0 && $fc !== $lastFaults[$d]) {
                $faultEvents[] = [
                    'Timestamp' => $row['Timestamp'],
                    'Drive' => $d,
                    'Fault_Code' => $fc,
                    'Description' => getFaultCodeDescription($fc)
                ];
            }
            
            // Update the tracker so identical consecutive numbers are ignored
            $lastFaults[$d] = $fc;
        }
    }
    
    // Sort array by Timestamp DESC (newest at the top)
    usort($faultEvents, function($a, $b) {
        return strtotime($b['Timestamp']) - strtotime($a['Timestamp']);
    });
    
    $totalEvents = count($faultEvents);

    // CSV Export
    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="fault_history_' . $craneId . '_' . $driveFilter . '_' . $fromDate . '_to_' . $toDate . '.csv"');
        $output = fopen('php://output', 'w');
        
        fputcsv($output, ['Timestamp', 'Drive', 'Fault Code ID', 'Fault Description']);
        foreach ($faultEvents as $event) {
            fputcsv($output, [$event['Timestamp'], $event['Drive'], $event['Fault_Code'], $event['Description']]);
        }
        
        fclose($output);
        exit;
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<!-- Page Header -->
<div class="row mb-4 align-items-center">
    <div class="col">
        <h1 class="page-title text-danger">
            <i class="bi bi-exclamation-triangle"></i> Fault History
        </h1>
        <p class="text-muted mb-0">De-duplicated log of critical VFD fault events</p>
    </div>
</div>

<!-- Report Filters -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card" id="fault-report-preview">
            <h3 class="card-title text-uppercase"><i class="bi bi-funnel"></i> Fault Filters</h3>
            <form method="GET" action="fault_reports.php" class="report-filter-form">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 mb-2">
                        <label class="settings-label" for="filter-crane">Crane</label>
                        <select class="form-select form-input-custom py-2" id="filter-crane" name="crane_id" required>
                            <option value="">Select Crane</option>
                            <?php foreach ($cranes as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['crane_id']); ?>" <?php echo $craneId === $c['crane_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo $c['crane_id']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="settings-label" for="filter-drive">Drive (Optional)</label>
                        <select class="form-select form-input-custom py-2" id="filter-drive" name="drive">
                            <option value="all" <?php echo $driveFilter==='all'?'selected':''; ?>>All Drives</option>
                            <option value="MH" <?php echo $driveFilter==='MH'?'selected':''; ?>>Main Hoist (MH)</option>
                            <option value="CT" <?php echo $driveFilter==='CT'?'selected':''; ?>>Cross Travel (CT)</option>
                            <option value="LT" <?php echo $driveFilter==='LT'?'selected':''; ?>>Long Travel (LT)</option>
                            <option value="AH" <?php echo $driveFilter==='AH'?'selected':''; ?>>Aux Hoist (AH)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="settings-label" for="filter-from">From Date</label>
                        <input type="date" class="form-control form-input-custom py-2" id="filter-from" name="from" value="<?php echo $fromDate; ?>">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="settings-label" for="filter-to">To Date</label>
                        <input type="date" class="form-control form-input-custom py-2" id="filter-to" name="to" value="<?php echo $toDate; ?>">
                    </div>
                    <div class="col-md-6 mb-2">
                        <button type="submit" class="btn btn-danger w-100 py-3" style="font-weight: 700; border-radius:12px; border:none; background: linear-gradient(135deg, #e53935 0%, #b71c1c 100%); color:white;">
                            <i class="bi bi-search"></i> Extract Faults
                        </button>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php if ($craneId && $totalEvents > 0): ?>
                        <a href="fault_reports.php?crane_id=<?php echo urlencode($craneId); ?>&drive=<?php echo $driveFilter; ?>&from=<?php echo $fromDate; ?>&to=<?php echo $toDate; ?>&export=csv" 
                           class="btn btn-success-gradient w-100 py-3" style="font-weight: 700;">
                            <i class="bi bi-download"></i> Download CSV
                        </a>
                        <?php else: ?>
                        <button class="btn btn-outline-action w-100 py-3" disabled style="font-weight: 700;">
                            <i class="bi bi-download"></i> Download CSV
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Fault Results -->
<?php if ($craneId): ?>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card" id="fault-table-card">
            <div class="card-header-bar" style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="card-title-group">
                        <h3 class="card-title text-uppercase mb-0">
                            Faults: <?php echo $driveFilter === 'all' ? 'All Drives' : $driveFilter . ' Drive'; ?>
                        </h3>
                        <span class="status-chip <?php echo $totalEvents > 0 ? 'status-offline' : 'status-idle-chip'; ?>" style="font-weight:700;">
                            <?php echo number_format($totalEvents); ?> faults recorded
                        </span>
                    </div>
                    <span class="text-muted" style="font-size:13px; display:inline-block; margin-top:4px;">
                        <?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?>
                    </span>
                </div>
                <button type="button" class="btn btn-outline-action btn-sm" onclick="toggleFullscreen()" id="btn-fullscreen">
                    <i class="bi bi-arrows-fullscreen"></i> Expand
                </button>
            </div>
            
            <?php if (empty($faultEvents)): ?>
            <div class="text-center" style="padding:40px;">
                <i class="bi bi-check-circle-fill" style="font-size:36px;color:#2e7d32;"></i>
                <p style="color:#74777f;margin-top:12px;">No faults recorded for the selected timeframe and parameters. System is healthy!</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" style="max-height:600px; max-width: 100%; overflow: auto;">
                <table class="table table-custom table-sm mb-0">
                    <thead style="position:sticky;top:0;z-index:1;">
                        <tr>
                            <th>DateTime</th>
                            <th>Drive</th>
                            <th>Raw ID</th>
                            <th>Fault Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faultEvents as $event): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($event['Timestamp']); ?></td>
                            <td><span class="badge bg-dark"><?php echo htmlspecialchars($event['Drive']); ?></span></td>
                            <td><code><?php echo htmlspecialchars($event['Fault_Code']); ?></code></td>
                            <td><span class="badge bg-danger text-wrap" style="font-size:12px;"><?php echo htmlspecialchars($event['Description']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleFullscreen() {
    const card = document.getElementById('fault-table-card');
    const btn = document.getElementById('btn-fullscreen');
    
    if (card.classList.contains('fullscreen-overlay')) {
        card.classList.remove('fullscreen-overlay');
        btn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i> Expand';
    } else {
        card.classList.add('fullscreen-overlay');
        btn.innerHTML = '<i class="bi bi-fullscreen-exit"></i> Collapse';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
