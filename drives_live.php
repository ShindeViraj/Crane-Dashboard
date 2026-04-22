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

$pageTitle = 'Drives Live Data';
$pdo = getDbConnection();
$craneInfo = $pdo->prepare("SELECT * FROM cranes WHERE crane_id = :cid");
$craneInfo->execute([':cid' => $craneId]);
$crane = $craneInfo->fetch();
$craneName = $crane ? $crane['name'] : 'Crane ' . $craneId;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$drives = [
    ['prefix' => 'MH', 'name' => 'Main Hoist', 'short' => 'MH', 'color' => '#E67E22', 'class' => 'drive-mh'],
    ['prefix' => 'CT', 'name' => 'Cross Travel', 'short' => 'CT', 'color' => '#3498DB', 'class' => 'drive-ct'],
    ['prefix' => 'LT', 'name' => 'Long Travel', 'short' => 'LT', 'color' => '#95A5A6', 'class' => 'drive-lt'],
    ['prefix' => 'AH', 'name' => 'Aux Hoist', 'short' => 'AH', 'color' => '#F1C40F', 'class' => 'drive-ah'],
];
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="crane_live.php?crane_id=<?php echo $craneId; ?>"><?php echo htmlspecialchars($craneName); ?></a></li>
        <li class="breadcrumb-item active">Drives Live Data</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($craneName); ?> — Drives Live Data</h1>
        <div class="last-update-inline">
            <span class="live-dot-sm"></span>
            <span>Last updated: <strong id="drives-last-update">Waiting for data...</strong></span>
        </div>
    </div>
    <a href="crane_live.php?crane_id=<?php echo $craneId; ?>" class="btn btn-outline-action" id="btn-back-overview">
        <i class="bi bi-arrow-left"></i> Back to Overview
    </a>
</div>

<!-- 4 Drive Cards (2x2 grid) -->
<div class="row g-4 mb-4">
    <?php foreach ($drives as $drive): ?>
    <div class="col-xl-6 mb-4">
        <div class="drive-card h-100" style="border-left-color: <?php echo $drive['color']; ?>;">
            <div class="drive-card-header">
                <div>
                    <div class="drive-mech-label"><?php echo $drive['prefix'] === 'MH' ? 'Hoist Mechanism' : ($drive['prefix'] === 'CT' ? 'Trolley Mechanism' : ($drive['prefix'] === 'LT' ? 'Gantry Mechanism' : 'Secondary Hoist')); ?></div>
                    <h3 class="drive-card-title"><?php echo $drive['name']; ?> (<?php echo $drive['prefix']; ?>)</h3>
                </div>
                <div>
                    <span class="status-chip status-idle-chip" id="<?php echo strtolower($drive['prefix']); ?>-drive-status-chip" style="font-size: 10px; font-weight: bold; text-transform: uppercase;">
                        <span id="<?php echo strtolower($drive['prefix']); ?>-drive-status-text">Idle (0)</span>
                    </span>
                </div>
            </div>
            <div class="param-grid">
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-activity"></i> Output Frequency</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-output-freq">— <small>Hz</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-lightning"></i> Motor Current</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-motor-current">— <small>A</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-arrow-repeat"></i> Motor Torque</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-motor-torque">— <small>Nm</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-plug"></i> Mains Voltage</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-mains-voltage">— <small>V</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-cpu"></i> Motor Voltage</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-motor-voltage">— <small>V</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-lightning-charge"></i> Motor Power</span>
                    <span class="param-value param-highlight" id="<?php echo strtolower($drive['prefix']); ?>-motor-power">— <small>kW</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-thermometer-half"></i> Drive Temp</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-drive-temp">— <small>°C</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-clock-history"></i> Run Time</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-run-time">— <small>hrs</small></span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-box-arrow-in-right"></i> Logic Input</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-logic-in">—</span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-box-arrow-right"></i> Logic Output</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-logic-out">—</span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-exclamation-triangle"></i> Fault Code</span>
                    <span class="param-value param-fault" id="<?php echo strtolower($drive['prefix']); ?>-fault-code">—</span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-speedometer"></i> Encoder</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-encoder">—</span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-database"></i> Load Data</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-load-data">—</span>
                </div>
                <div class="param-row">
                    <span class="param-label"><i class="bi bi-toggle-on"></i> DI</span>
                    <span class="param-value" id="<?php echo strtolower($drive['prefix']); ?>-di">—</span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
const CRANE_ID = '<?php echo $craneId; ?>';
const DRIVES = ['mh', 'ct', 'lt', 'ah'];
const PREFIXES = ['MH', 'CT', 'LT', 'AH'];

<?php 
require_once 'includes/fault_codes.php';
global $faultMap;
?>
const FAULT_MAP = <?php echo json_encode($faultMap); ?>;

function updateDrivesLive(data) {
    if (!data) return;
    
    document.getElementById('drives-last-update').textContent = data.Timestamp || '—';
    
    DRIVES.forEach((d, i) => {
        const p = PREFIXES[i];
        
        // Status
        const status = parseInt(data[p + '_Drive_status']) || 0;
        const statusChip = document.getElementById(d + '-drive-status-chip');
        const statusText = document.getElementById(d + '-drive-status-text');
        statusText.textContent = status > 0 ? 'Running (' + status + ')' : 'Idle (0)';
        statusChip.className = 'status-chip ' + (status > 0 ? 'status-online' : 'status-idle-chip');
        
        // Parameters
        const setVal = (id, val, unit) => {
            const el = document.getElementById(id);
            if (el) {
                const v = val !== null && val !== undefined ? val : '—';
                el.innerHTML = v + (unit ? ' <small>' + unit + '</small>' : '');
            }
        };
        
        setVal(d + '-output-freq', data[p + '_Output_frequency'], 'Hz');
        setVal(d + '-motor-current', data[p + '_Motor_current'], 'A');
        setVal(d + '-motor-torque', data[p + '_Motor_torque'], 'Nm');
        setVal(d + '-mains-voltage', data[p + '_Mains_voltage'], 'V');
        setVal(d + '-motor-voltage', data[p + '_Motor_voltage'], 'V');
        
        const volt = parseFloat(data[p + '_Motor_voltage']) || 0;
        const curr = parseFloat(data[p + '_Motor_current']) || 0;
        const power = (volt * curr * 1.732 / 1000).toFixed(2);
        setVal(d + '-motor-power', power, 'kW');
        
        setVal(d + '-run-time', data[p + '_Motion_run_time'], 'hrs');
        setVal(d + '-logic-in', data[p + '_Logic_input'], '');
        setVal(d + '-logic-out', data[p + '_Logic_output'], '');
        setVal(d + '-encoder', data[p + '_Encoder'], '');
        setVal(d + '-load-data', data[p + '_Load_data'], '');
        setVal(d + '-di', data[p + '_di'], '');
        
        // Drive temp with color coding
        const temp = parseFloat(data[p + '_Drive_temp']) || 0;
        const tempEl = document.getElementById(d + '-drive-temp');
        if (tempEl) {
            tempEl.innerHTML = temp + ' <small>°C</small>';
            tempEl.classList.remove('temp-warning', 'temp-danger');
            if (temp > 70) tempEl.classList.add('temp-danger');
            else if (temp > 50) tempEl.classList.add('temp-warning');
        }
        
        // Fault code with red highlight
        const faultCode = parseInt(data[p + '_Altivar_fault_code']) || 0;
        const faultEl = document.getElementById(d + '-fault-code');
        if (faultEl) {
            if (faultCode > 0) {
                const faultStr = FAULT_MAP[faultCode] ? FAULT_MAP[faultCode] : 'Unknown (' + faultCode + ')';
                faultEl.innerHTML = '<span class="badge bg-danger text-wrap" style="font-size:11px;">' + faultStr + '</span>';
                faultEl.classList.add('fault-active');
            } else {
                faultEl.innerHTML = '—';
                faultEl.classList.remove('fault-active');
            }
        }
    });
}

function pollDrives() {
    fetch('api/get_latest.php?crane_id=' + CRANE_ID)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                updateDrivesLive(res.data);
            }
        })
        .catch(err => console.warn('Poll error:', err));
}

pollDrives();
setInterval(pollDrives, 3000);
</script>

<?php require_once 'includes/footer.php'; ?>
