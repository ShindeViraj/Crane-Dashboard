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

$pageTitle = 'Crane Live Dashboard';
$pdo = getDbConnection();
$craneInfo = $pdo->prepare("SELECT crane_id, name, location, description, total_life_hours FROM cranes WHERE crane_id = :cid");
$craneInfo->execute([':cid' => $craneId]);
$crane = $craneInfo->fetch();
$craneName = $crane ? $crane['name'] : 'Crane ' . $craneId;
$totalLifeHours = $crane ? (float)($crane['total_life_hours'] ?? 0) : 0;

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item">Crane List</li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($craneName); ?> Live</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="page-header">
    <h1 class="page-title"><?php echo htmlspecialchars($craneName); ?> — Live Dashboard</h1>
    <a href="drives_live.php?crane_id=<?php echo $craneId; ?>" class="btn btn-success-gradient" id="btn-drives-live">
        <i class="bi bi-speedometer2"></i> View Motion Live Data
    </a>
</div>

<!-- 4-Column Cards -->
<div class="row g-4 mb-4">
    <!-- Motion Wise Utilization -->
    <div class="col-lg-3">
        <div class="data-card chart-card">
            <h3 class="card-title text-uppercase">Motion Wise Utilization</h3>
            <div class="chart-container">
                <canvas id="motionPieChart"></canvas>
            </div>
            <div class="chart-legend-custom" id="pie-legend">
                <span class="legend-item"><span class="legend-dot" style="background:#E67E22;"></span> MH — <span id="legend-mh-hrs">0</span> hrs</span>
                <span class="legend-item"><span class="legend-dot" style="background:#3498DB;"></span> CT — <span id="legend-ct-hrs">0</span> hrs</span>
                <span class="legend-item"><span class="legend-dot" style="background:#95A5A6;"></span> LT — <span id="legend-lt-hrs">0</span> hrs</span>
                <span class="legend-item"><span class="legend-dot" style="background:#F1C40F;"></span> AH — <span id="legend-ah-hrs">0</span> hrs</span>
            </div>
        </div>
    </div>
    
    <!-- Total Energy Consumed - Donut Chart -->
    <div class="col-lg-3">
        <div class="data-card chart-card">
            <h3 class="card-title text-uppercase">Total Energy Consumed</h3>
            <div class="chart-container" style="position:relative;">
                <canvas id="energyDonutChart"></canvas>
                <div id="energy-center-label" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-55%);text-align:center;pointer-events:none;">
                    <span id="energy-total-value" style="font-size:1.5rem;font-weight:700;color:var(--text-primary,#1a1a2e);">—</span><br>
                    <span style="font-size:0.75rem;font-weight:500;color:var(--text-secondary,#6c757d);">kWh</span>
                </div>
            </div>
            <div class="power-timestamp" style="text-align:center;margin-top:0.5rem;">
                <i class="bi bi-clock"></i>
                <span id="energy-update-time">Waiting for data...</span>
            </div>
        </div>
    </div>

    <!-- Remaining Crane Life -->
    <div class="col-lg-3">
        <div class="data-card" id="crane-life-card">
            <h3 class="card-title text-uppercase">Remaining Crane Life</h3>
            <div id="crane-life-content">
                <?php if ($totalLifeHours > 0): ?>
                <div style="margin-top:1rem;">
                    <!-- Semi-circular gauge via SVG -->
                    <div style="text-align:center;">
                        <svg id="life-gauge-svg" viewBox="0 0 200 120" width="100%" style="max-width:220px;">
                            <!-- Background arc -->
                            <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#e0e0e0" stroke-width="14" stroke-linecap="round"/>
                            <!-- Foreground arc -->
                            <path id="life-gauge-arc" d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#27ae60" stroke-width="14" stroke-linecap="round"/>
                            <!-- Percentage text -->
                            <text id="life-gauge-pct" x="100" y="85" text-anchor="middle" font-size="22" font-weight="700" fill="#27ae60">—%</text>
                            <text x="100" y="105" text-anchor="middle" font-size="10" fill="#6c757d">remaining</text>
                        </svg>
                    </div>
                    <div style="text-align:center;margin-top:0.5rem;">
                        <div style="font-size:0.85rem;color:var(--text-secondary,#6c757d);">
                            Used: <strong id="life-used-hrs">0</strong> hrs / Total: <strong><?php echo number_format($totalLifeHours, 0); ?></strong> hrs
                        </div>
                        <div style="font-size:0.9rem;font-weight:600;margin-top:0.25rem;" id="life-remaining-text">
                            Remaining: — hrs (—%)
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align:center;margin-top:2rem;color:var(--text-secondary,#6c757d);">
                    <i class="bi bi-exclamation-circle" style="font-size:2rem;display:block;margin-bottom:0.5rem;"></i>
                    Total life not configured
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Motion Wise Power Consumption (kWh) -->
    <div class="col-lg-3">
        <div class="data-card drive-power-card">
            <h3 class="card-title text-uppercase">Motion Wise Power Consumption</h3>
            <div class="drive-power-list">
                <div class="drive-power-row">
                    <span class="drive-power-label">
                        <span class="legend-dot" style="background:#E67E22;"></span> MH
                    </span>
                    <span class="drive-power-value" id="mh-power-kwh">— kWh</span>
                </div>
                <div class="drive-power-row">
                    <span class="drive-power-label">
                        <span class="legend-dot" style="background:#3498DB;"></span> CT
                    </span>
                    <span class="drive-power-value" id="ct-power-kwh">— kWh</span>
                </div>
                <div class="drive-power-row">
                    <span class="drive-power-label">
                        <span class="legend-dot" style="background:#95A5A6;"></span> LT
                    </span>
                    <span class="drive-power-value" id="lt-power-kwh">— kWh</span>
                </div>
                <div class="drive-power-row">
                    <span class="drive-power-label">
                        <span class="legend-dot" style="background:#F1C40F;"></span> AH
                    </span>
                    <span class="drive-power-value" id="ah-power-kwh">— kWh</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daily Energy Consumption Chart -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card">
            <h3 class="card-title">Daily Energy Consumption – Last 30 Days</h3>
            <div class="chart-container chart-wide">
                <canvas id="powerLineChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const CRANE_ID = '<?php echo $craneId; ?>';
const TOTAL_LIFE_HOURS = <?php echo $totalLifeHours; ?>;

// Safe numeric parser — preserves negative values, only defaults to 0 for missing/NaN
const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };

// ============ MOTION PIE CHART ============
const pieCtx = document.getElementById('motionPieChart').getContext('2d');
const motionPieChart = new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: ['Main Hoist', 'Cross Travel', 'Long Travel', 'Aux Hoist'],
        datasets: [{
            data: [25, 25, 25, 25],
            backgroundColor: ['#E67E22', '#3498DB', '#95A5A6', '#F1C40F'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '55%',
        plugins: {
            legend: { display: false }
        }
    }
});

// ============ ENERGY DONUT CHART ============
const energyCtx = document.getElementById('energyDonutChart').getContext('2d');
const energyDonutChart = new Chart(energyCtx, {
    type: 'doughnut',
    data: {
        labels: ['MH', 'CT', 'LT', 'AH'],
        datasets: [{
            data: [0, 0, 0, 0],
            backgroundColor: ['#E67E22', '#3498DB', '#95A5A6', '#F1C40F'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return ctx.label + ': ' + ctx.parsed.toFixed(2) + ' kWh';
                    }
                }
            }
        }
    }
});

// ============ LINE CHART ============
const lineCtx = document.getElementById('powerLineChart').getContext('2d');
const powerLineChart = new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            {
                label: 'Total Energy (kWh)',
                data: [],
                borderColor: '#006e25',
                backgroundColor: 'rgba(0, 110, 37, 0.08)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#006e25'
            },
            {
                label: 'MH',
                data: [],
                borderColor: '#E67E22',
                borderWidth: 1.5,
                tension: 0.4,
                pointRadius: 0,
                borderDash: [5, 5]
            },
            {
                label: 'CT',
                data: [],
                borderColor: '#3498DB',
                borderWidth: 1.5,
                tension: 0.4,
                pointRadius: 0,
                borderDash: [5, 5]
            },
            {
                label: 'LT',
                data: [],
                borderColor: '#95A5A6',
                borderWidth: 1.5,
                tension: 0.4,
                pointRadius: 0,
                borderDash: [5, 5]
            },
            {
                label: 'AH',
                data: [],
                borderColor: '#F1C40F',
                borderWidth: 1.5,
                tension: 0.4,
                pointRadius: 0,
                borderDash: [5, 5]
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { 
                beginAtZero: true, 
                title: { display: true, text: 'Energy (kWh)', font: { family: 'Inter' } },
                grid: { color: 'rgba(0,0,0,0.04)' }
            },
            x: { 
                title: { display: true, text: 'Date', font: { family: 'Inter' } },
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

// ============ REMAINING LIFE GAUGE ============
function updateLifeGauge(usedHours) {
    if (TOTAL_LIFE_HOURS <= 0) return;
    const remaining = Math.max(0, TOTAL_LIFE_HOURS - usedHours);
    const pctRemaining = (remaining / TOTAL_LIFE_HOURS) * 100;

    // Determine color: green >50%, yellow 25-50%, red <25%
    let color = '#27ae60';
    if (pctRemaining < 25) color = '#e74c3c';
    else if (pctRemaining < 50) color = '#f39c12';

    // Update SVG arc — semicircle from left to right
    // The arc total length is π × r = π × 80 ≈ 251.33
    const arcLength = Math.PI * 80;
    const filledLength = arcLength * (1 - (pctRemaining / 100));
    const gaugeArc = document.getElementById('life-gauge-arc');
    if (gaugeArc) {
        gaugeArc.setAttribute('stroke', color);
        gaugeArc.setAttribute('stroke-dasharray', arcLength);
        gaugeArc.setAttribute('stroke-dashoffset', filledLength);
    }

    const gaugeText = document.getElementById('life-gauge-pct');
    if (gaugeText) {
        gaugeText.textContent = pctRemaining.toFixed(1) + '%';
        gaugeText.setAttribute('fill', color);
    }

    const usedEl = document.getElementById('life-used-hrs');
    if (usedEl) usedEl.textContent = Math.round(usedHours).toLocaleString();

    const remEl = document.getElementById('life-remaining-text');
    if (remEl) {
        remEl.textContent = 'Remaining: ' + Math.round(remaining).toLocaleString() + ' hrs (' + pctRemaining.toFixed(1) + '%)';
        remEl.style.color = color;
    }
}

// ============ LIVE DATA POLLING ============
function updateCraneLive(data) {
    if (!data) return;
    
    // Energy consumed (kWh) from di values
    const mhEnergy = num(data.MH_di);
    const ctEnergy = num(data.CT_di);
    const ltEnergy = num(data.LT_di);
    const ahEnergy = num(data.AH_di);
    const totalEnergy = mhEnergy + ctEnergy + ltEnergy + ahEnergy;
    
    // Motion Wise Power Consumption (kWh from di values)
    document.getElementById('mh-power-kwh').textContent = mhEnergy.toFixed(2) + ' kWh';
    document.getElementById('ct-power-kwh').textContent = ctEnergy.toFixed(2) + ' kWh';
    document.getElementById('lt-power-kwh').textContent = ltEnergy.toFixed(2) + ' kWh';
    document.getElementById('ah-power-kwh').textContent = ahEnergy.toFixed(2) + ' kWh';

    // Energy Donut Chart
    energyDonutChart.data.datasets[0].data = [mhEnergy, ctEnergy, ltEnergy, ahEnergy];
    energyDonutChart.update('none');
    document.getElementById('energy-total-value').textContent = totalEnergy.toFixed(2);
    document.getElementById('energy-update-time').textContent = data.Timestamp || '—';
    
    // Motion Pie Chart - update with run times
    const mhRun = num(data.MH_Motion_run_time) || 1;
    const ctRun = num(data.CT_Motion_run_time) || 1;
    const ltRun = num(data.LT_Motion_run_time) || 1;
    const ahRun = num(data.AH_Motion_run_time) || 1;
    motionPieChart.data.datasets[0].data = [mhRun, ctRun, ltRun, ahRun];
    motionPieChart.update('none');

    // Update legend with run hour values
    document.getElementById('legend-mh-hrs').textContent = Math.round(mhRun).toLocaleString();
    document.getElementById('legend-ct-hrs').textContent = Math.round(ctRun).toLocaleString();
    document.getElementById('legend-lt-hrs').textContent = Math.round(ltRun).toLocaleString();
    document.getElementById('legend-ah-hrs').textContent = Math.round(ahRun).toLocaleString();

    // Update remaining crane life gauge
    const totalRunHours = num(data.MH_Motion_run_time) + num(data.CT_Motion_run_time) + num(data.LT_Motion_run_time) + num(data.AH_Motion_run_time);
    updateLifeGauge(totalRunHours);
}

function pollCraneLive() {
    fetch('api/get_latest.php?crane_id=' + CRANE_ID)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                updateCraneLive(res.data);
            }
        })
        .catch(err => console.warn('Poll error:', err));
}

// Load historical chart data
function loadHistoryChart() {
    fetch('api/get_history.php?crane_id=' + CRANE_ID)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data && res.data.length > 0) {
                const labels = res.data.map(d => d.date);
                powerLineChart.data.labels = labels;
                // Note: API returns avg power values; using as energy proxy until API is updated for true daily kWh
                powerLineChart.data.datasets[0].data = res.data.map(d => num(d.avg_total_power));
                powerLineChart.data.datasets[1].data = res.data.map(d => num(d.avg_mh_power));
                powerLineChart.data.datasets[2].data = res.data.map(d => num(d.avg_ct_power));
                powerLineChart.data.datasets[3].data = res.data.map(d => num(d.avg_lt_power));
                powerLineChart.data.datasets[4].data = res.data.map(d => num(d.avg_ah_power));
                powerLineChart.update();
            }
        })
        .catch(err => console.warn('History error:', err));
}

pollCraneLive();
loadHistoryChart();
setInterval(pollCraneLive, 500);
</script>

<?php require_once 'includes/footer.php'; ?>
