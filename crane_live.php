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
$craneInfo = $pdo->prepare("SELECT crane_id, name, location, description FROM cranes WHERE crane_id = :cid");
$craneInfo->execute([':cid' => $craneId]);
$crane = $craneInfo->fetch();
$craneName = $crane ? $crane['name'] : 'Crane ' . $craneId;

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
        <i class="bi bi-speedometer2"></i> View Drives Live Data
    </a>
</div>

<!-- 3-Column Cards -->
<div class="row g-4 mb-4">
    <!-- Motion Wise Utilization -->
    <div class="col-lg-4">
        <div class="data-card chart-card">
            <h3 class="card-title text-uppercase">Motion Wise Utilization</h3>
            <div class="chart-container">
                <canvas id="motionPieChart"></canvas>
            </div>
            <div class="chart-legend-custom" id="pie-legend">
                <span class="legend-item"><span class="legend-dot" style="background:#E67E22;"></span> MH</span>
                <span class="legend-item"><span class="legend-dot" style="background:#3498DB;"></span> CT</span>
                <span class="legend-item"><span class="legend-dot" style="background:#95A5A6;"></span> LT</span>
                <span class="legend-item"><span class="legend-dot" style="background:#F1C40F;"></span> AH</span>
            </div>
        </div>
    </div>
    
    <!-- Total Power Consumption -->
    <div class="col-lg-4">
        <div class="data-card power-hero-card">
            <h3 class="card-title text-uppercase">Total Power Consumption</h3>
            <div class="power-hero">
                <span class="power-value" id="total-power-value">—</span>
                <span class="power-unit">kWh</span>
            </div>
            <div class="power-timestamp">
                <i class="bi bi-clock"></i>
                <span id="power-update-time">Waiting for data...</span>
            </div>
        </div>
    </div>
    
    <!-- Drive Wise Power Consumption -->
    <div class="col-lg-4">
        <div class="data-card drive-power-card">
            <h3 class="card-title text-uppercase">Drive Wise Power Consumption</h3>
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

<!-- Power Consumption Chart -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="data-card">
            <h3 class="card-title">Total Power Consumption – Last 30 Days</h3>
            <div class="chart-container chart-wide">
                <canvas id="powerLineChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const CRANE_ID = '<?php echo $craneId; ?>';

// ============ PIE CHART ============
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

// ============ LINE CHART ============
const lineCtx = document.getElementById('powerLineChart').getContext('2d');
const powerLineChart = new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: [],
        datasets: [
            {
                label: 'Total Power (kW)',
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
                title: { display: true, text: 'Power (kW)', font: { family: 'Inter' } },
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

// ============ LIVE DATA POLLING ============
// Calculate power from live voltage × current data (P = V × I × √3 / 1000 for 3-phase, in kW)
function calcPower(voltage, current) {
    const v = parseFloat(voltage) || 0;
    const i = parseFloat(current) || 0;
    return (v * i * 1.732) / 1000; // 3-phase power in kW
}

function updateCraneLive(data) {
    if (!data) return;
    
    // Calculate power from voltage × current for each drive
    const mhPower = calcPower(data.MH_Motor_voltage, data.MH_Motor_current);
    const ctPower = calcPower(data.CT_Motor_voltage, data.CT_Motor_current);
    const ltPower = calcPower(data.LT_Motor_voltage, data.LT_Motor_current);
    const ahPower = calcPower(data.AH_Motor_voltage, data.AH_Motor_current);
    const totalPower = mhPower + ctPower + ltPower + ahPower;
    
    // Total Power Hero
    document.getElementById('total-power-value').textContent = totalPower.toFixed(2);
    document.getElementById('power-update-time').textContent = data.Timestamp || '—';
    
    // Drive Wise Power
    document.getElementById('mh-power-kwh').textContent = mhPower.toFixed(2) + ' kW';
    document.getElementById('ct-power-kwh').textContent = ctPower.toFixed(2) + ' kW';
    document.getElementById('lt-power-kwh').textContent = ltPower.toFixed(2) + ' kW';
    document.getElementById('ah-power-kwh').textContent = ahPower.toFixed(2) + ' kW';
    
    // Motion Pie Chart - update with run times
    const mhRun = parseFloat(data.MH_Motion_run_time) || 1;
    const ctRun = parseFloat(data.CT_Motion_run_time) || 1;
    const ltRun = parseFloat(data.LT_Motion_run_time) || 1;
    const ahRun = parseFloat(data.AH_Motion_run_time) || 1;
    motionPieChart.data.datasets[0].data = [mhRun, ctRun, ltRun, ahRun];
    motionPieChart.update('none');
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
                powerLineChart.data.datasets[0].data = res.data.map(d => parseFloat(d.avg_total_power) || 0);
                powerLineChart.data.datasets[1].data = res.data.map(d => parseFloat(d.avg_mh_power) || 0);
                powerLineChart.data.datasets[2].data = res.data.map(d => parseFloat(d.avg_ct_power) || 0);
                powerLineChart.data.datasets[3].data = res.data.map(d => parseFloat(d.avg_lt_power) || 0);
                powerLineChart.data.datasets[4].data = res.data.map(d => parseFloat(d.avg_ah_power) || 0);
                powerLineChart.update();
            }
        })
        .catch(err => console.warn('History error:', err));
}

pollCraneLive();
loadHistoryChart();
setInterval(pollCraneLive, 3000);
</script>

<?php require_once 'includes/footer.php'; ?>
