<?php
$pageTitle = 'Dashboard';
require_once 'includes/auth.php';
requireLogin();

$pdo = getDbConnection();

// Fetch all cranes
$cranes = $pdo->query("SELECT c.*, (SELECT MAX(cd.Timestamp) FROM crane_data cd WHERE cd.crane_id = c.crane_id) as last_data_at FROM cranes c ORDER BY c.crane_id")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><i class="bi bi-grid-1x2-fill"></i> Dashboard</li>
        <li class="breadcrumb-item active">Overview</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title">Dashboard Overview</h1>
</div>

<!-- Summary Cards Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="summary-card">
            <div class="summary-icon" style="background: linear-gradient(135deg, #002147, #003d7a);">
                <i class="bi bi-gear-wide-connected"></i>
            </div>
            <div class="summary-body">
                <span class="summary-label">Total Cranes</span>
                <span class="summary-value"><?php echo count($cranes); ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="summary-card">
            <div class="summary-icon" style="background: linear-gradient(135deg, #006e25, #00a63a);">
                <i class="bi bi-activity"></i>
            </div>
            <div class="summary-body">
                <span class="summary-label">Online Cranes</span>
                <span class="summary-value" id="online-cranes">0</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="summary-card">
            <div class="summary-icon" style="background: linear-gradient(135deg, #F57C00, #FF9800);">
                <i class="bi bi-lightning-charge-fill"></i>
            </div>
            <div class="summary-body">
                <span class="summary-label">Total Power</span>
                <span class="summary-value" id="dash-total-power">—</span>
                <span class="summary-unit">kW</span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="summary-card">
            <div class="summary-icon" style="background: linear-gradient(135deg, #ba1a1a, #e53935);">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="summary-body">
                <span class="summary-label">Active Faults</span>
                <span class="summary-value" id="dash-faults">0</span>
            </div>
        </div>
    </div>
</div>

<!-- Crane Cards -->
<div class="section-header">
    <h2 class="section-title">Crane Monitoring</h2>
</div>

<div class="row g-4 mb-4">
<?php foreach ($cranes as $crane): 
    $lastData = $crane['last_data_at'] ? strtotime($crane['last_data_at']) : 0;
    $isOnline = (time() - $lastData) < 50;
?>
    <div class="col-lg-12">
        <div class="data-card crane-overview-card" data-crane-id="<?php echo htmlspecialchars($crane['crane_id']); ?>">
            <div class="card-header-bar">
                <div class="card-title-group">
                    <h3 class="card-title" style="font-size:20px;font-weight:800;color:#002147;margin:0;">
                        <?php echo htmlspecialchars($crane['name']); ?>
                    </h3>
                    <span class="status-chip <?php echo $isOnline ? 'status-online' : 'status-idle-chip'; ?>" id="crane-status-<?php echo $crane['crane_id']; ?>">
                        <span class="status-dot"></span> <span class="crane-status-text"><?php echo $isOnline ? 'Online' : 'Offline'; ?></span>
                    </span>
                    <?php if ($crane['location']): ?>
                    <span style="font-size:12px;color:#74777f;"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($crane['location']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-actions">
                    <a href="crane_live.php?crane_id=<?php echo urlencode($crane['crane_id']); ?>" class="btn btn-primary-gradient">
                        <i class="bi bi-display"></i> Live Dashboard
                    </a>
                    <a href="drives_live.php?crane_id=<?php echo urlencode($crane['crane_id']); ?>" class="btn btn-outline-action">
                        <i class="bi bi-speedometer2"></i> Drives Data
                    </a>
                    <a href="reports.php?crane_id=<?php echo urlencode($crane['crane_id']); ?>" class="btn btn-outline-action">
                        <i class="bi bi-file-bar-graph"></i> Reports
                    </a>
                </div>
            </div>
            
            <div class="row g-4 mt-2">
                <div class="col-md-3">
                    <div class="drive-mini-card drive-mh">
                        <div class="drive-mini-accent"></div>
                        <div class="drive-mini-header">
                            <span class="drive-mini-name">Main Hoist (MH)</span>
                            <span class="drive-mini-status" id="<?php echo $crane['crane_id']; ?>-mh-status-dot"><i class="bi bi-circle-fill"></i></span>
                        </div>
                        <div class="drive-mini-stats">
                            <div class="mini-stat"><span class="mini-stat-label">Frequency</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-mh-freq">— Hz</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Current</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-mh-current">— A</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Power</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-mh-power">— kW</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Temp</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-mh-temp">— °C</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="drive-mini-card drive-ct">
                        <div class="drive-mini-accent"></div>
                        <div class="drive-mini-header">
                            <span class="drive-mini-name">Cross Travel (CT)</span>
                            <span class="drive-mini-status" id="<?php echo $crane['crane_id']; ?>-ct-status-dot"><i class="bi bi-circle-fill"></i></span>
                        </div>
                        <div class="drive-mini-stats">
                            <div class="mini-stat"><span class="mini-stat-label">Frequency</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ct-freq">— Hz</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Current</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ct-current">— A</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Power</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ct-power">— kW</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Temp</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ct-temp">— °C</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="drive-mini-card drive-lt">
                        <div class="drive-mini-accent"></div>
                        <div class="drive-mini-header">
                            <span class="drive-mini-name">Long Travel (LT)</span>
                            <span class="drive-mini-status" id="<?php echo $crane['crane_id']; ?>-lt-status-dot"><i class="bi bi-circle-fill"></i></span>
                        </div>
                        <div class="drive-mini-stats">
                            <div class="mini-stat"><span class="mini-stat-label">Frequency</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-lt-freq">— Hz</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Current</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-lt-current">— A</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Power</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-lt-power">— kW</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Temp</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-lt-temp">— °C</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="drive-mini-card drive-ah">
                        <div class="drive-mini-accent"></div>
                        <div class="drive-mini-header">
                            <span class="drive-mini-name">Aux Hoist (AH)</span>
                            <span class="drive-mini-status" id="<?php echo $crane['crane_id']; ?>-ah-status-dot"><i class="bi bi-circle-fill"></i></span>
                        </div>
                        <div class="drive-mini-stats">
                            <div class="mini-stat"><span class="mini-stat-label">Frequency</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ah-freq">— Hz</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Current</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ah-current">— A</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Power</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ah-power">— kW</span></div>
                            <div class="mini-stat"><span class="mini-stat-label">Temp</span><span class="mini-stat-value" id="<?php echo $crane['crane_id']; ?>-ah-temp">— °C</span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="last-update-bar">
                <i class="bi bi-clock"></i>
                <span>Last updated: <strong id="<?php echo $crane['crane_id']; ?>-last-update"><?php echo $crane['last_data_at'] ?: '—'; ?></strong></span>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
const CRANE_IDS = <?php echo json_encode(array_column($cranes, 'crane_id')); ?>;
const OFFLINE_TIMEOUT = 50000; // 50 seconds in ms

function updateDashboardOverview() {
    let totalPower = 0;
    let totalFaults = 0;
    let onlineCount = 0;
    
    CRANE_IDS.forEach(craneId => {
        fetch('api/get_latest.php?crane_id=' + encodeURIComponent(craneId))
            .then(r => r.json())
            .then(res => {
                if (!res.success || !res.data) return;
                const data = res.data;
                
                // Check online status (50s timeout)
                const dataTime = new Date(data.Timestamp).getTime();
                const now = Date.now();
                const isOnline = (now - dataTime) < OFFLINE_TIMEOUT;
                
                const statusEl = document.getElementById('crane-status-' + craneId);
                if (statusEl) {
                    statusEl.className = 'status-chip ' + (isOnline ? 'status-online' : 'status-idle-chip');
                    statusEl.querySelector('.crane-status-text').textContent = isOnline ? 'Online' : 'Offline';
                }
                if (isOnline) onlineCount++;
                
                // Drive mini stats
                ['mh','ct','lt','ah'].forEach((d, i) => {
                    const p = ['MH','CT','LT','AH'][i];
                    const prefix = craneId + '-' + d;
                    const el = (id) => document.getElementById(prefix + '-' + id);
                    if (el('freq')) el('freq').textContent = (data[p+'_Output_frequency'] || '—') + ' Hz';
                    if (el('current')) el('current').textContent = (data[p+'_Motor_current'] || '—') + ' A';
                    
                    const volt = parseFloat(data[p+'_Motor_voltage']) || 0;
                    const curr = parseFloat(data[p+'_Motor_current']) || 0;
                    const drivePower = (volt * curr * 1.732 / 1000);
                    
                    if (el('power')) el('power').textContent = drivePower.toFixed(2) + ' kW';
                    if (el('temp')) el('temp').textContent = (data[p+'_Drive_temp'] || '—') + ' °C';
                    
                    const statusDot = el('status-dot');
                    if (statusDot) {
                        const s = parseInt(data[p+'_Drive_status']) || 0;
                        statusDot.className = 'drive-mini-status ' + (s > 0 ? 'status-running' : 'status-idle');
                    }
                });
                
                // Power & Faults
                const calcP = (v, c) => (parseFloat(data[v])||0) * (parseFloat(data[c])||0) * 1.732 / 1000;
                const p = calcP('MH_Motor_voltage', 'MH_Motor_current') + 
                          calcP('CT_Motor_voltage', 'CT_Motor_current') +
                          calcP('LT_Motor_voltage', 'LT_Motor_current') + 
                          calcP('AH_Motor_voltage', 'AH_Motor_current');
                totalPower += p;
                
                ['MH','CT','LT','AH'].forEach(d => {
                    if (parseInt(data[d+'_Altivar_fault_code']) > 0) totalFaults++;
                });
                
                // Timestamp
                const updateEl = document.getElementById(craneId + '-last-update');
                if (updateEl) updateEl.textContent = data.Timestamp || '—';
                
                // Update summaries
                document.getElementById('dash-total-power').textContent = totalPower.toFixed(1);
                document.getElementById('dash-faults').textContent = totalFaults;
                document.getElementById('online-cranes').textContent = onlineCount;
            })
            .catch(() => {});
    });
}

updateDashboardOverview();
setInterval(updateDashboardOverview, 3000);
</script>

<?php require_once 'includes/footer.php'; ?>
