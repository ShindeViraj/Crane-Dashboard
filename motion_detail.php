<?php
require_once 'includes/auth.php';
requireLogin();

$craneId = isset($_GET['crane_id']) ? htmlspecialchars($_GET['crane_id']) : '1';
$motion = isset($_GET['motion']) ? strtoupper(htmlspecialchars($_GET['motion'])) : 'MH';
if (!in_array($motion, ['MH', 'CT', 'LT', 'AH'])) $motion = 'MH';
if (!canAccessCrane($craneId)) {
    $_SESSION['flash_error'] = 'You do not have access to this crane.';
    header('Location: dashboard.php');
    exit;
}

$motionNames = ['MH' => 'Main Hoist', 'CT' => 'Cross Travel', 'LT' => 'Long Travel', 'AH' => 'Aux Hoist'];
$motionColors = ['MH' => '#E67E22', 'CT' => '#3498DB', 'LT' => '#95A5A6', 'AH' => '#F1C40F'];
$motionName = $motionNames[$motion];
$motionColor = $motionColors[$motion];

$pageTitle = $motionName . ' Detail';
$pdo = getDbConnection();
$craneInfo = $pdo->prepare("SELECT crane_id, name FROM cranes WHERE crane_id = :cid");
$craneInfo->execute([':cid' => $craneId]);
$crane = $craneInfo->fetch();
$craneName = $crane ? $crane['name'] : 'Crane ' . $craneId;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
/* ===== Premium Gauge Design ===== */
.gauge-card {
    text-align: center;
    padding: 24px 16px 20px;
}
.gauge-title {
    font-size: 13px;
    font-weight: 700;
    color: #002147;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 16px;
}
.gauge-svg-wrapper {
    position: relative;
    width: 200px;
    height: 120px;
    margin: 0 auto;
}
.gauge-svg-wrapper svg {
    width: 200px;
    height: 120px;
}
.gauge-center-value {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
}
.gauge-center-value .value {
    font-size: 32px;
    font-weight: 800;
    font-family: 'Inter', sans-serif;
    color: #002147;
    line-height: 1;
}
.gauge-center-value .unit {
    font-size: 13px;
    font-weight: 500;
    color: #8c8c8c;
    display: block;
    margin-top: 2px;
}
.gauge-range-labels {
    display: flex;
    justify-content: space-between;
    padding: 4px 20px 0;
    font-size: 11px;
    font-weight: 600;
    color: #aaa;
}

/* ===== Stat Cards ===== */
.stat-card-body {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 0;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
    flex-shrink: 0;
}
.stat-info { flex: 1; }
.stat-label {
    font-size: 12px;
    font-weight: 600;
    color: #5f6368;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-value {
    font-size: 26px;
    font-weight: 800;
    font-family: 'Inter', sans-serif;
    color: #002147;
    line-height: 1.2;
}
.stat-unit {
    font-size: 14px;
    font-weight: 500;
    color: #8c8c8c;
    margin-left: 4px;
}

/* ===== Trend Chart ===== */
.trend-chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item"><a href="crane_live.php?crane_id=<?php echo $craneId; ?>"><?php echo htmlspecialchars($craneName); ?></a></li>
        <li class="breadcrumb-item"><a href="drives_live.php?crane_id=<?php echo $craneId; ?>">Motion Live Data</a></li>
        <li class="breadcrumb-item active"><?php echo $motionName; ?> Detail</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($craneName); ?> — <?php echo $motionName; ?> (<?php echo $motion; ?>)</h1>
        <div class="last-update-inline">
            <span class="live-dot-sm"></span>
            <span>Last updated: <strong id="motion-last-update">Waiting for data...</strong></span>
        </div>
    </div>
    <a href="drives_live.php?crane_id=<?php echo $craneId; ?>" class="btn btn-outline-action" id="btn-back-drives">
        <i class="bi bi-arrow-left"></i> Back to Drives
    </a>
</div>

<!-- Row 1: 6 SVG Gauges -->
<div class="row g-4 mb-4">
    <?php
    $gauges = [
        ['id' => 'gauge-freq',    'title' => 'Output Frequency',    'unit' => 'Hz',  'max' => 100, 'icon' => 'bi-activity',          'color' => '#E67E22'],
        ['id' => 'gauge-current', 'title' => 'Motor Current',       'unit' => 'A',   'max' => 100, 'icon' => 'bi-lightning',          'color' => '#2ecc71'],
        ['id' => 'gauge-torque',  'title' => 'Motor Torque',        'unit' => '%',   'max' => 100, 'icon' => 'bi-arrow-repeat',       'color' => '#3498DB'],
        ['id' => 'gauge-voltage', 'title' => 'Motor Voltage',       'unit' => 'V',   'max' => 500, 'icon' => 'bi-cpu',                'color' => '#9b59b6'],
        ['id' => 'gauge-power',   'title' => 'Motor Load / Power',  'unit' => '%',   'max' => 100, 'icon' => 'bi-lightning-charge',    'color' => '#F1C40F'],
        ['id' => 'gauge-temp',    'title' => 'Temperature',         'unit' => '°C',  'max' => 100, 'icon' => 'bi-thermometer-half',    'color' => '#e74c3c'],
    ];
    foreach ($gauges as $g):
    ?>
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="data-card gauge-card" id="<?php echo $g['id']; ?>">
            <div class="gauge-title"><i class="bi <?php echo $g['icon']; ?>"></i> <?php echo $g['title']; ?></div>
            <div class="gauge-svg-wrapper">
                <svg viewBox="0 0 200 120" xmlns="http://www.w3.org/2000/svg">
                    <!-- Background track (grey arc) -->
                    <path d="M 20 100 A 80 80 0 0 1 180 100" 
                          fill="none" stroke="#e9ecef" stroke-width="14" stroke-linecap="round"/>
                    <!-- Value arc (colored) -->
                    <path class="gauge-arc" d="M 20 100 A 80 80 0 0 1 180 100" 
                          fill="none" stroke="<?php echo $g['color']; ?>" stroke-width="14" stroke-linecap="round"
                          stroke-dasharray="0 251.33"
                          style="transition: stroke-dasharray 0.8s cubic-bezier(0.4, 0, 0.2, 1);"/>
                    <!-- Tick marks -->
                    <line x1="20" y1="100" x2="28" y2="100" stroke="#ccc" stroke-width="1.5"/>
                    <line x1="55" y1="32" x2="60" y2="38" stroke="#ccc" stroke-width="1.5"/>
                    <line x1="100" y1="20" x2="100" y2="28" stroke="#ccc" stroke-width="1.5"/>
                    <line x1="145" y1="32" x2="140" y2="38" stroke="#ccc" stroke-width="1.5"/>
                    <line x1="180" y1="100" x2="172" y2="100" stroke="#ccc" stroke-width="1.5"/>
                    <!-- Center dot -->
                    <circle cx="100" cy="100" r="6" fill="#002147"/>
                    <!-- Needle -->
                    <line class="gauge-needle" x1="100" y1="100" x2="30" y2="100" 
                          stroke="#002147" stroke-width="3" stroke-linecap="round"
                          style="transform-origin: 100px 100px; transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);"/>
                </svg>
                <div class="gauge-center-value">
                    <span class="value">0</span>
                    <span class="unit"><?php echo $g['unit']; ?></span>
                </div>
            </div>
            <div class="gauge-range-labels">
                <span>0</span>
                <span><?php echo $g['max']; ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Row 2: Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card">
            <div class="stat-card-body">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6c5ce7, #a29bfe);"><i class="bi bi-plug"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Mains Voltage</div>
                    <div><span class="stat-value" id="stat-mains-v">—</span><span class="stat-unit">V</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card">
            <div class="stat-card-body">
                <div class="stat-icon" style="background: linear-gradient(135deg, #00b894, #55efc4);"><i class="bi bi-clock-history"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Run Time</div>
                    <div><span class="stat-value" id="stat-runtime">—</span><span class="stat-unit">hrs</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card">
            <div class="stat-card-body">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e, #e17055);"><i class="bi bi-battery-charging"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Energy Consumed</div>
                    <div><span class="stat-value" id="stat-energy">—</span><span class="stat-unit">kWh</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card">
            <div class="stat-card-body">
                <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #fd79a8);"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Fault Code</div>
                    <div><span class="stat-value" id="stat-fault">—</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card">
            <div class="stat-card-body">
                <div class="stat-icon" style="background: linear-gradient(135deg, #0984e3, #74b9ff);"><i class="bi bi-speedometer"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Encoder</div>
                    <div><span class="stat-value" id="stat-encoder">—</span></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card">
            <div class="stat-card-body">
                <div class="stat-icon" style="background: linear-gradient(135deg, #636e72, #b2bec3);"><i class="bi bi-database"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Load Data</div>
                    <div><span class="stat-value" id="stat-load">—</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Live Trend Chart -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card">
            <h3 class="card-title text-uppercase">Live Trend — <?php echo $motionName; ?></h3>
            <div class="trend-chart-container">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const CRANE_ID = '<?php echo $craneId; ?>';
const MOTION = '<?php echo $motion; ?>';
const MOTION_COLOR = '<?php echo $motionColor; ?>';

<?php
require_once 'includes/fault_codes.php';
global $faultMap;
?>
const FAULT_MAP = <?php echo json_encode($faultMap); ?>;

const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };

// ===== SVG Gauge Update =====
function updateGauge(id, value, max) {
    const pct = Math.min(Math.max(value / max, 0), 1);
    const el = document.getElementById(id);
    if (!el) return;

    // Update arc — total arc length is ~251.33 (π * 80)
    const arcLen = 251.33;
    const filledLen = pct * arcLen;
    const arc = el.querySelector('.gauge-arc');
    if (arc) arc.setAttribute('stroke-dasharray', filledLen + ' ' + arcLen);

    // Update needle — rotate from -90deg (0%) to +90deg (100%)
    const degrees = -90 + (pct * 180);
    const needle = el.querySelector('.gauge-needle');
    if (needle) needle.style.transform = 'rotate(' + degrees + 'deg)';

    // Update value text
    const valEl = el.querySelector('.gauge-center-value .value');
    if (valEl) valEl.textContent = value % 1 === 0 ? value : value.toFixed(1);
}

// ===== Trend Chart =====
const MAX_POINTS = 30;
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            {
                label: 'Frequency (Hz)',
                data: [],
                borderColor: '#E67E22',
                backgroundColor: '#E67E2222',
                fill: true,
                tension: 0.4,
                borderWidth: 2.5,
                pointRadius: 2,
                pointBackgroundColor: '#E67E22'
            },
            {
                label: 'Current (A)',
                data: [],
                borderColor: '#2ecc71',
                backgroundColor: '#2ecc7122',
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 0
            },
            {
                label: 'Power (%)',
                data: [],
                borderColor: '#F1C40F',
                backgroundColor: '#F1C40F22',
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 0
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 300 },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Value', font: { family: 'Inter' } },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            x: {
                title: { display: true, text: 'Time', font: { family: 'Inter' } },
                grid: { display: false }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: { font: { family: 'Inter', size: 11 }, usePointStyle: true }
            }
        }
    }
});

// ===== Live Data Update =====
function updateMotionDetail(data) {
    const P = MOTION;

    document.getElementById('motion-last-update').textContent = data.Timestamp || '—';

    // Gauges
    updateGauge('gauge-freq', num(data[P + '_Output_frequency']), 100);
    updateGauge('gauge-current', num(data[P + '_Motor_current']), 100);
    updateGauge('gauge-torque', num(data[P + '_Motor_torque']), 100);
    updateGauge('gauge-voltage', num(data[P + '_Motor_voltage']), 500);
    updateGauge('gauge-power', num(data[P + '_Motor_power']), 100);
    updateGauge('gauge-temp', num(data[P + '_Drive_temp']), 100);

    // Stat cards
    document.getElementById('stat-mains-v').textContent = num(data[P + '_Mains_voltage']).toFixed(1);
    document.getElementById('stat-runtime').textContent = num(data[P + '_Motion_run_time']).toFixed(0);
    document.getElementById('stat-energy').textContent = num(data[P + '_di']).toFixed(1);
    document.getElementById('stat-encoder').textContent = data[P + '_Encoder'] || '—';
    document.getElementById('stat-load').textContent = data[P + '_Load_data'] || '—';

    // Fault code
    const faultCode = num(data[P + '_Altivar_fault_code']);
    const faultEl = document.getElementById('stat-fault');
    if (faultCode > 0) {
        const faultStr = FAULT_MAP[faultCode] ? FAULT_MAP[faultCode] : 'Unknown (' + faultCode + ')';
        faultEl.innerHTML = '<span class="badge bg-danger text-wrap" style="font-size:13px;">' + faultStr + '</span>';
    } else {
        faultEl.textContent = 'None';
    }

    // Trend chart
    const now = new Date().toLocaleTimeString();
    const freqVal = num(data[P + '_Output_frequency']);
    const currVal = num(data[P + '_Motor_current']);
    const powVal = num(data[P + '_Motor_power']);

    trendChart.data.labels.push(now);
    trendChart.data.datasets[0].data.push(freqVal);
    trendChart.data.datasets[1].data.push(currVal);
    trendChart.data.datasets[2].data.push(powVal);

    if (trendChart.data.labels.length > MAX_POINTS) {
        trendChart.data.labels.shift();
        trendChart.data.datasets[0].data.shift();
        trendChart.data.datasets[1].data.shift();
        trendChart.data.datasets[2].data.shift();
    }

    trendChart.update('none');
}

function pollMotion() {
    fetch('api/get_latest.php?crane_id=' + CRANE_ID)
        .then(r => r.json())
        .then(res => { if (res.success && res.data) updateMotionDetail(res.data); })
        .catch(err => console.warn('Poll error:', err));
}

pollMotion();
setInterval(pollMotion, 500);
</script>

<?php require_once 'includes/footer.php'; ?>
