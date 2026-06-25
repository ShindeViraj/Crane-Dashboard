<?php
require_once 'includes/auth.php';
requireLogin();

// Verify crane access for 'user' role
$craneId = isset($_GET['crane_id']) ? htmlspecialchars($_GET['crane_id']) : '1';
if (!canAccessCrane($craneId)) {
    $_SESSION['flash_error'] = 'You do not have access to this crane.';
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Select Motion';
$pdo = getDbConnection();
$craneInfo = $pdo->prepare("SELECT crane_id, name, location, description FROM cranes WHERE crane_id = :cid");
$craneInfo->execute([':cid' => $craneId]);
$crane = $craneInfo->fetch();
$craneName = $crane ? $crane['name'] : 'Crane ' . $craneId;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$drives = [
    ['prefix' => 'MH', 'name' => 'Main Hoist',  'mech' => 'Hoist Mechanism',     'icon' => 'bi-arrow-up-circle',    'color' => '#E67E22'],
    ['prefix' => 'CT', 'name' => 'Cross Travel', 'mech' => 'Trolley Mechanism',   'icon' => 'bi-arrow-left-right',   'color' => '#3498DB'],
    ['prefix' => 'LT', 'name' => 'Long Travel',  'mech' => 'Gantry Mechanism',    'icon' => 'bi-arrows-move',        'color' => '#95A5A6'],
    ['prefix' => 'AH', 'name' => 'Aux Hoist',    'mech' => 'Secondary Hoist',     'icon' => 'bi-arrow-up-square',    'color' => '#F1C40F'],
];
?>

<style>
.motion-select-card {
    display: block;
    text-decoration: none;
    color: inherit;
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e9ecef;
    border-left: 5px solid var(--accent);
    padding: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}
.motion-select-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    color: inherit;
    text-decoration: none;
}
.motion-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px 12px;
}
.motion-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #fff;
}
.motion-card-info {
    flex: 1;
    margin-left: 14px;
}
.motion-card-mech {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #8c8c8c;
}
.motion-card-name {
    font-size: 20px;
    font-weight: 800;
    color: #002147;
    margin: 0;
    line-height: 1.2;
}
.motion-card-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}
.motion-card-body {
    padding: 8px 24px 20px;
}
.motion-stat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
}
.motion-stat {
    text-align: center;
    padding: 10px 4px;
    background: #f8f9fb;
    border-radius: 10px;
}
.motion-stat-value {
    font-size: 20px;
    font-weight: 800;
    color: #002147;
    line-height: 1;
}
.motion-stat-value small {
    font-size: 11px;
    font-weight: 500;
    color: #8c8c8c;
}
.motion-stat-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #8c8c8c;
    margin-top: 4px;
}
.motion-stat-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 6px;
    overflow: hidden;
}
.motion-stat-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 0.6s ease;
}
.motion-card-footer {
    background: #f8f9fb;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #eee;
}
.motion-card-footer span {
    font-size: 13px;
    font-weight: 600;
    color: var(--accent);
}
.motion-card-footer i {
    font-size: 16px;
    color: var(--accent);
    transition: transform 0.3s ease;
}
.motion-select-card:hover .motion-card-footer i {
    transform: translateX(4px);
}
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="crane_live.php?crane_id=<?php echo $craneId; ?>"><?php echo htmlspecialchars($craneName); ?></a></li>
        <li class="breadcrumb-item active">Select Motion</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($craneName); ?> — Motion Data</h1>
        <div class="last-update-inline">
            <span class="live-dot-sm"></span>
            <span>Last updated: <strong id="drives-last-update">Waiting for data...</strong></span>
        </div>
    </div>
    <a href="crane_live.php?crane_id=<?php echo $craneId; ?>" class="btn btn-outline-action" id="btn-back-overview">
        <i class="bi bi-arrow-left"></i> Back to Overview
    </a>
</div>

<p style="color:#5f6368;font-size:14px;margin-bottom:20px;">
    <i class="bi bi-info-circle"></i> Select a motion to view its detailed dashboard with live gauges, charts, and parameters.
</p>

<!-- 4 Motion Cards (2x2 grid) -->
<div class="row g-4 mb-4">
    <?php foreach ($drives as $drive): ?>
    <div class="col-xl-6 col-lg-6 mb-3">
        <a href="motion_detail.php?crane_id=<?php echo $craneId; ?>&motion=<?php echo $drive['prefix']; ?>" 
           class="motion-select-card" style="--accent: <?php echo $drive['color']; ?>;">
            
            <div class="motion-card-header">
                <div class="motion-card-icon" style="background: <?php echo $drive['color']; ?>;">
                    <i class="bi <?php echo $drive['icon']; ?>"></i>
                </div>
                <div class="motion-card-info">
                    <div class="motion-card-mech"><?php echo $drive['mech']; ?></div>
                    <h3 class="motion-card-name"><?php echo $drive['name']; ?> (<?php echo $drive['prefix']; ?>)</h3>
                </div>
                <span class="motion-card-status status-chip status-idle-chip" 
                      id="<?php echo strtolower($drive['prefix']); ?>-drive-status-chip">
                    <span id="<?php echo strtolower($drive['prefix']); ?>-drive-status-text">Idle (0)</span>
                </span>
            </div>
            
            <div class="motion-card-body">
                <div class="motion-stat-grid">
                    <div class="motion-stat">
                        <div class="motion-stat-value" id="<?php echo strtolower($drive['prefix']); ?>-output-freq">— <small>Hz</small></div>
                        <div class="motion-stat-label">Frequency</div>
                        <div class="motion-stat-bar"><div class="motion-stat-bar-fill" id="<?php echo strtolower($drive['prefix']); ?>-bar-freq" style="width:0%;background:<?php echo $drive['color']; ?>;"></div></div>
                    </div>
                    <div class="motion-stat">
                        <div class="motion-stat-value" id="<?php echo strtolower($drive['prefix']); ?>-motor-current">— <small>A</small></div>
                        <div class="motion-stat-label">Current</div>
                        <div class="motion-stat-bar"><div class="motion-stat-bar-fill" id="<?php echo strtolower($drive['prefix']); ?>-bar-current" style="width:0%;background:<?php echo $drive['color']; ?>;"></div></div>
                    </div>
                    <div class="motion-stat">
                        <div class="motion-stat-value" id="<?php echo strtolower($drive['prefix']); ?>-motor-power">— <small>%</small></div>
                        <div class="motion-stat-label">Power</div>
                        <div class="motion-stat-bar"><div class="motion-stat-bar-fill" id="<?php echo strtolower($drive['prefix']); ?>-bar-power" style="width:0%;background:<?php echo $drive['color']; ?>;"></div></div>
                    </div>
                    <div class="motion-stat">
                        <div class="motion-stat-value" id="<?php echo strtolower($drive['prefix']); ?>-drive-temp">— <small>°C</small></div>
                        <div class="motion-stat-label">Temperature</div>
                        <div class="motion-stat-bar"><div class="motion-stat-bar-fill" id="<?php echo strtolower($drive['prefix']); ?>-bar-temp" style="width:0%;background:<?php echo $drive['color']; ?>;"></div></div>
                    </div>
                    <div class="motion-stat">
                        <div class="motion-stat-value" id="<?php echo strtolower($drive['prefix']); ?>-run-time">— <small>hrs</small></div>
                        <div class="motion-stat-label">Run Time</div>
                    </div>
                    <div class="motion-stat">
                        <div class="motion-stat-value" id="<?php echo strtolower($drive['prefix']); ?>-di">— <small>kWh</small></div>
                        <div class="motion-stat-label">Energy</div>
                    </div>
                </div>
            </div>
            
            <div class="motion-card-footer">
                <span>View Detailed Dashboard</span>
                <i class="bi bi-arrow-right"></i>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<script>
const CRANE_ID = '<?php echo $craneId; ?>';
const DRIVES = ['mh', 'ct', 'lt', 'ah'];
const PREFIXES = ['MH', 'CT', 'LT', 'AH'];

// Safe numeric parser
const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };

function updateDrivesLive(data, ageSeconds) {
    if (!data) return;
    
    const isOnline = ageSeconds !== null && ageSeconds !== undefined ? (ageSeconds < 50) : true;
    
    const lastUpdateEl = document.getElementById('drives-last-update');
    if (lastUpdateEl) {
        if (isOnline) {
            lastUpdateEl.textContent = data.Timestamp || '—';
            lastUpdateEl.style.color = '';
        } else {
            lastUpdateEl.innerHTML = (data.Timestamp || '—') + ' <span class="badge bg-danger ms-1" style="font-size: 11px;">Offline</span>';
            lastUpdateEl.style.color = '#e74c3c';
        }
    }
    
    DRIVES.forEach((d, i) => {
        const p = PREFIXES[i];
        
        // Status
        const status = isOnline ? num(data[p + '_Drive_status']) : 0;
        const statusChip = document.getElementById(d + '-drive-status-chip');
        const statusText = document.getElementById(d + '-drive-status-text');
        
        if (isOnline) {
            statusText.textContent = status > 0 ? 'Running (' + status + ')' : 'Idle (0)';
            statusChip.className = 'motion-card-status status-chip ' + (status > 0 ? 'status-online' : 'status-idle-chip');
        } else {
            statusText.textContent = 'Offline';
            statusChip.className = 'motion-card-status status-chip status-idle-chip';
        }
        
        // Value setter
        const setVal = (id, val, unit) => {
            const el = document.getElementById(id);
            if (el) {
                const v = val !== null && val !== undefined && val !== '' ? val : '—';
                el.innerHTML = v + (unit ? ' <small>' + unit + '</small>' : '');
            }
        };
        
        setVal(d + '-output-freq', isOnline ? data[p + '_Output_frequency'] : '0', 'Hz');
        setVal(d + '-motor-current', isOnline ? data[p + '_Motor_current'] : '0', 'A');
        setVal(d + '-motor-power', isOnline ? data[p + '_Motor_power'] : '0', '%');
        setVal(d + '-run-time', data[p + '_Motion_run_time'], 'hrs');
        setVal(d + '-di', data[p + '_di'], 'kWh');
        
        // Temp with color coding
        const temp = isOnline ? num(data[p + '_Drive_temp']) : 0;
        const tempEl = document.getElementById(d + '-drive-temp');
        if (tempEl) {
            tempEl.innerHTML = (isOnline ? temp : '0') + ' <small>°C</small>';
            tempEl.style.color = isOnline ? (temp > 70 ? '#e74c3c' : (temp > 50 ? '#f39c12' : '#002147')) : '#8c8c8c';
        }
        
        // Progress bars
        const setBar = (id, value, max) => {
            const bar = document.getElementById(id);
            if (bar) bar.style.width = isOnline ? (Math.min(Math.max(num(value) / max * 100, 0), 100) + '%') : '0%';
        };
        setBar(d + '-bar-freq', data[p + '_Output_frequency'], 100);
        setBar(d + '-bar-current', data[p + '_Motor_current'], 100);
        setBar(d + '-bar-power', data[p + '_Motor_power'], 100);
        setBar(d + '-bar-temp', data[p + '_Drive_temp'], 100);
    });
}

function pollDrives() {
    fetch('api/get_latest.php?crane_id=' + CRANE_ID)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                updateDrivesLive(res.data, res.age_seconds);
            }
        })
        .catch(err => console.warn('Poll error:', err));
}

pollDrives();
setInterval(pollDrives, 500);
</script>

<?php require_once 'includes/footer.php'; ?>
