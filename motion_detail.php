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

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
<style>
/* ===== Premium ApexCharts Gauge Design ===== */
.gauge-card {
    text-align: center;
    padding: 18px 14px 14px;
    border-radius: 14px;
    position: relative;
    overflow: hidden;
    border-left: 4px solid transparent;
    box-shadow: 0 2px 12px rgba(0,33,71,0.06);
    transition: box-shadow 0.3s ease, transform 0.3s ease;
}
.gauge-card:hover {
    box-shadow: 0 6px 24px rgba(0,33,71,0.12);
    transform: translateY(-2px);
}
.gauge-card.gc-freq { border-left-color: #E67E22; background: linear-gradient(135deg, #fffaf5 0%, #fff 60%); }
.gauge-card.gc-curr { border-left-color: #27ae60; background: linear-gradient(135deg, #f3fdf6 0%, #fff 60%); }
.gauge-card.gc-torq { border-left-color: #3498DB; background: linear-gradient(135deg, #f0f7ff 0%, #fff 60%); }
.gauge-card.gc-volt { border-left-color: #9b59b6; background: linear-gradient(135deg, #f8f3ff 0%, #fff 60%); }
.gauge-card.gc-pow  { border-left-color: #F1C40F; background: linear-gradient(135deg, #fffef3 0%, #fff 60%); }
.gauge-card.gc-temp { border-left-color: #e74c3c; background: linear-gradient(135deg, #fff5f5 0%, #fff 60%); }
.gauge-title {
    font-size: 11px;
    font-weight: 700;
    color: #002147;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.gauge-title i { font-size: 13px; opacity: 0.7; }
.gauge-apex-wrap {
    position: relative;
    margin: 0 auto;
    min-height: 180px;
}
.gauge-footer {
    display: flex;
    justify-content: space-between;
    padding: 0 18px;
    font-size: 10px;
    font-weight: 600;
    color: #b0b0b0;
    margin-top: -6px;
    letter-spacing: 0.3px;
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

<!-- Row 1: 6 ApexCharts Gauges -->
<div class="row g-4 mb-4">
    <!-- 1. Output Frequency — Gradient Radial -->
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card gauge-card gc-freq" id="gauge-freq">
            <div class="gauge-title"><i class="bi bi-activity"></i> Output Frequency</div>
            <div class="gauge-apex-wrap" id="chart-freq"></div>
            <div class="gauge-footer"><span>0</span><span>100 Hz</span></div>
        </div>
    </div>
    <!-- 2. Motor Current — Segmented Stroked -->
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card gauge-card gc-curr" id="gauge-current">
            <div class="gauge-title"><i class="bi bi-lightning"></i> Motor Current</div>
            <div class="gauge-apex-wrap" id="chart-current"></div>
            <div class="gauge-footer"><span>0</span><span>100 A</span></div>
        </div>
    </div>
    <!-- 3. Motor Torque — Classic Semi-Circle -->
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card gauge-card gc-torq" id="gauge-torque">
            <div class="gauge-title"><i class="bi bi-arrow-repeat"></i> Motor Torque</div>
            <div class="gauge-apex-wrap" id="chart-torque"></div>
            <div class="gauge-footer"><span>0</span><span>100 %</span></div>
        </div>
    </div>
    <!-- 4. Motor Voltage — Dual-Tone Radial -->
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card gauge-card gc-volt" id="gauge-voltage">
            <div class="gauge-title"><i class="bi bi-cpu"></i> Motor Voltage</div>
            <div class="gauge-apex-wrap" id="chart-voltage"></div>
            <div class="gauge-footer"><span>0</span><span>500 V</span></div>
        </div>
    </div>
    <!-- 5. Motor Load / Power — Thick Progress Ring -->
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card gauge-card gc-pow" id="gauge-power">
            <div class="gauge-title"><i class="bi bi-lightning-charge"></i> Motor Load / Power</div>
            <div class="gauge-apex-wrap" id="chart-power"></div>
            <div class="gauge-footer"><span>0</span><span>100 %</span></div>
        </div>
    </div>
    <!-- 6. Temperature — Heat Zone Gauge -->
    <div class="col-lg-4 col-md-6 mb-3">
        <div class="data-card gauge-card gc-temp" id="gauge-temp">
            <div class="gauge-title"><i class="bi bi-thermometer-half"></i> Temperature</div>
            <div class="gauge-apex-wrap" id="chart-temp"></div>
            <div class="gauge-footer"><span>0</span><span>100 °C</span></div>
        </div>
    </div>
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

// ===== ApexCharts Gauge Instances =====
const gaugeCharts = {};
const gaugeMaxes = { 'gauge-freq': 100, 'gauge-current': 100, 'gauge-torque': 100, 'gauge-voltage': 500, 'gauge-power': 100, 'gauge-temp': 100 };
const gaugeUnits = { 'gauge-freq': 'Hz', 'gauge-current': 'A', 'gauge-torque': '%', 'gauge-voltage': 'V', 'gauge-power': '%', 'gauge-temp': '°C' };

function makeBase(label, color, unit) {
    return {
        series: [0],
        chart: { type: 'radialBar', height: 210, sparkline: { enabled: true }, animations: { enabled: true, easing: 'easeinout', speed: 700 },
            dropShadow: { enabled: true, top: 2, left: 0, blur: 6, opacity: 0.08 } },
        plotOptions: { radialBar: {
            startAngle: -135, endAngle: 135,
            hollow: { size: '60%', background: 'transparent' },
            track: { background: '#eef0f4', strokeWidth: '97%', margin: 5,
                dropShadow: { enabled: true, top: 1, left: 0, blur: 3, opacity: 0.06 } },
            dataLabels: {
                name:  { offsetY: -10, fontSize: '11px', fontFamily: 'Inter', fontWeight: 600, color: '#8c9bac' },
                value: { offsetY: 4, fontSize: '26px', fontFamily: 'Inter', fontWeight: 800, color: '#002147',
                    formatter: function() { return '0'; }
                }
            }
        }},
        fill: { type: 'solid', colors: [color] },
        stroke: { lineCap: 'round' },
        labels: [label]
    };
}

// --- 1. Frequency: Orange-gold gradient arc ---
(function() {
    const o = makeBase('Frequency', '#E67E22', 'Hz');
    o.fill = { type: 'gradient', gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.35, gradientToColors: ['#F39C12'], stops: [0, 100] } };
    o.plotOptions.radialBar.track.background = '#fde8d0';
    o.plotOptions.radialBar.hollow.size = '62%';
    o.plotOptions.radialBar.dataLabels.value.formatter = function(v) { return (v / 100 * 100).toFixed(1) + ' Hz'; };
    gaugeCharts['gauge-freq'] = new ApexCharts(document.querySelector('#chart-freq'), o);
    gaugeCharts['gauge-freq'].render();
})();

// --- 2. Current: Dashed / segmented green ring ---
(function() {
    const o = makeBase('Current', '#27ae60', 'A');
    o.plotOptions.radialBar.startAngle = -120;
    o.plotOptions.radialBar.endAngle = 120;
    o.plotOptions.radialBar.track.background = '#d5f5e3';
    o.plotOptions.radialBar.hollow.size = '58%';
    o.plotOptions.radialBar.track.strokeWidth = '90%';
    o.stroke = { lineCap: 'butt', dashArray: 5 };
    o.fill = { type: 'gradient', gradient: { shade: 'dark', type: 'vertical', shadeIntensity: 0.2, gradientToColors: ['#1e8449'], stops: [0, 100] } };
    o.plotOptions.radialBar.dataLabels.value.formatter = function(v) { return (v / 100 * 100).toFixed(1) + ' A'; };
    gaugeCharts['gauge-current'] = new ApexCharts(document.querySelector('#chart-current'), o);
    gaugeCharts['gauge-current'].render();
})();

// --- 3. Torque: Blue gradient arc ---
(function() {
    const o = makeBase('Torque', '#3498DB', '%');
    o.plotOptions.radialBar.startAngle = -135;
    o.plotOptions.radialBar.endAngle = 135;
    o.plotOptions.radialBar.hollow.size = '55%';
    o.plotOptions.radialBar.track.background = '#d4e6f9';
    o.plotOptions.radialBar.track.strokeWidth = '92%';
    o.fill = { type: 'gradient', gradient: { shade: 'dark', type: 'vertical', shadeIntensity: 0.3, gradientToColors: ['#1a5276'], stops: [0, 100] } };
    o.plotOptions.radialBar.dataLabels.value.formatter = function(v) { return (v / 100 * 100).toFixed(1) + ' %'; };
    gaugeCharts['gauge-torque'] = new ApexCharts(document.querySelector('#chart-torque'), o);
    gaugeCharts['gauge-torque'].render();
})();

// --- 4. Voltage: Purple diagonal gradient, wide arc ---
(function() {
    const o = makeBase('Voltage', '#9b59b6', 'V');
    o.plotOptions.radialBar.hollow.size = '60%';
    o.plotOptions.radialBar.track.background = '#e8daef';
    o.plotOptions.radialBar.startAngle = -150;
    o.plotOptions.radialBar.endAngle = 150;
    o.fill = { type: 'gradient', gradient: { shade: 'dark', type: 'diagonal1', shadeIntensity: 0.4, gradientToColors: ['#6c3483'], stops: [0, 100] } };
    o.plotOptions.radialBar.dataLabels.value.formatter = function(v) { return (v / 100 * 500).toFixed(0) + ' V'; };
    gaugeCharts['gauge-voltage'] = new ApexCharts(document.querySelector('#chart-voltage'), o);
    gaugeCharts['gauge-voltage'].render();
})();

// --- 5. Power: Thick bold ring, gold-to-orange ---
(function() {
    const o = makeBase('Load', '#F1C40F', '%');
    o.plotOptions.radialBar.startAngle = -135;
    o.plotOptions.radialBar.endAngle = 135;
    o.plotOptions.radialBar.hollow.size = '50%';
    o.plotOptions.radialBar.track.background = '#fef3cd';
    o.plotOptions.radialBar.track.strokeWidth = '100%';
    o.stroke = { lineCap: 'round' };
    o.fill = { type: 'gradient', gradient: { shade: 'dark', type: 'horizontal', shadeIntensity: 0.2, gradientToColors: ['#e67e22'], stops: [0, 100] } };
    o.plotOptions.radialBar.dataLabels.value.fontSize = '30px';
    o.plotOptions.radialBar.dataLabels.value.formatter = function(v) { return (v / 100 * 100).toFixed(1) + ' %'; };
    gaugeCharts['gauge-power'] = new ApexCharts(document.querySelector('#chart-power'), o);
    gaugeCharts['gauge-power'].render();
})();

// --- 6. Temperature: Dynamic heat-zone colors ---
(function() {
    const o = makeBase('Temp', '#27ae60', '°C');
    o.plotOptions.radialBar.startAngle = -135;
    o.plotOptions.radialBar.endAngle = 135;
    o.plotOptions.radialBar.hollow.size = '58%';
    o.plotOptions.radialBar.track.background = '#fce4e4';
    o.colors = ['#27ae60'];
    o.fill = { type: 'solid', colors: ['#27ae60'] };
    o.plotOptions.radialBar.dataLabels.value.formatter = function(v) { return (v / 100 * 100).toFixed(1) + ' °C'; };
    gaugeCharts['gauge-temp'] = new ApexCharts(document.querySelector('#chart-temp'), o);
    gaugeCharts['gauge-temp'].render();
})();

// ===== Gauge Update Helper =====
function updateGauge(id, value, max) {
    const chart = gaugeCharts[id];
    if (!chart) return;
    const pct = Math.min(Math.max(value / max * 100, 0), 100);
    chart.updateSeries([Math.round(pct * 10) / 10], true);

    // Dynamic color for temperature gauge
    if (id === 'gauge-temp') {
        let col = '#27ae60';
        if (pct > 70) col = '#e74c3c';
        else if (pct > 40) col = '#f39c12';
        chart.updateOptions({ fill: { colors: [col] }, colors: [col] }, false, false);
    }
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
function updateMotionDetail(data, ageSeconds) {
    const P = MOTION;
    const isOnline = ageSeconds !== null && ageSeconds !== undefined ? (ageSeconds < 50) : true;

    const lastUpdateEl = document.getElementById('motion-last-update');
    if (lastUpdateEl) {
        if (isOnline) {
            lastUpdateEl.textContent = data.Timestamp || '—';
            lastUpdateEl.style.color = '';
        } else {
            lastUpdateEl.innerHTML = (data.Timestamp || '—') + ' <span class="badge bg-danger ms-1" style="font-size: 11px;">Offline</span>';
            lastUpdateEl.style.color = '#e74c3c';
        }
    }

    // Gauges
    updateGauge('gauge-freq', isOnline ? num(data[P + '_Output_frequency']) : 0, 100);
    updateGauge('gauge-current', isOnline ? num(data[P + '_Motor_current']) : 0, 100);
    updateGauge('gauge-torque', isOnline ? num(data[P + '_Motor_torque']) : 0, 100);
    updateGauge('gauge-voltage', isOnline ? num(data[P + '_Motor_voltage']) : 0, 500);
    updateGauge('gauge-power', isOnline ? num(data[P + '_Motor_power']) : 0, 100);
    updateGauge('gauge-temp', isOnline ? num(data[P + '_Drive_temp']) : 0, 100);

    // Stat cards
    document.getElementById('stat-mains-v').textContent = isOnline ? num(data[P + '_Mains_voltage']).toFixed(1) : '0';
    document.getElementById('stat-runtime').textContent = num(data[P + '_Motion_run_time']).toFixed(0);
    document.getElementById('stat-energy').textContent = num(data[P + '_di']).toFixed(1);
    document.getElementById('stat-encoder').textContent = isOnline ? (data[P + '_Encoder'] || '—') : '—';
    document.getElementById('stat-load').textContent = isOnline ? (data[P + '_Load_data'] || '—') : '—';

    // Fault code
    const faultCode = isOnline ? num(data[P + '_Altivar_fault_code']) : 0;
    const faultEl = document.getElementById('stat-fault');
    if (faultCode > 0) {
        const faultStr = FAULT_MAP[faultCode] ? FAULT_MAP[faultCode] : 'Unknown (' + faultCode + ')';
        faultEl.innerHTML = '<span class="badge bg-danger text-wrap" style="font-size:13px;">' + faultStr + '</span>';
    } else {
        faultEl.textContent = 'None';
    }

    // Trend chart
    const now = new Date().toLocaleTimeString();
    const freqVal = isOnline ? num(data[P + '_Output_frequency']) : 0;
    const currVal = isOnline ? num(data[P + '_Motor_current']) : 0;
    const powVal = isOnline ? num(data[P + '_Motor_power']) : 0;

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
        .then(res => { if (res.success && res.data) updateMotionDetail(res.data, res.age_seconds); })
        .catch(err => console.warn('Poll error:', err));
}

pollMotion();
setInterval(pollMotion, 500);
</script>

<?php require_once 'includes/footer.php'; ?>
