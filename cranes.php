<?php
/**
 * View All Cranes — list all cranes with status
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'View All Cranes';
$pdo = getDbConnection();

// Fetch all cranes with latest data timestamp
$stmt = $pdo->query("
    SELECT c.*, 
           (SELECT MAX(cd.Timestamp) FROM crane_data cd WHERE cd.crane_id = c.crane_id) as last_data_at
    FROM cranes c 
    ORDER BY c.crane_id ASC
");
$cranes = $stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item active">Crane List</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title">All Cranes</h1>
    <a href="manage_cranes.php" class="btn btn-primary-gradient" id="btn-add-crane-top">
        <i class="bi bi-plus-lg"></i> Add Crane
    </a>
</div>

<div class="row g-4 mb-4">
    <?php if (empty($cranes)): ?>
    <div class="col-12">
        <div class="data-card text-center" style="padding:60px;">
            <i class="bi bi-inbox" style="font-size:48px;color:#c4c6cf;"></i>
            <p style="color:#74777f;margin-top:16px;">No cranes configured yet. <a href="manage_cranes.php">Add your first crane</a>.</p>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($cranes as $crane): 
        $lastData = $crane['last_data_at'] ? strtotime($crane['last_data_at']) : 0;
        $isOnline = (time() - $lastData) < 50; // 50-second timeout
        $statusClass = $isOnline ? 'status-online' : 'status-idle-chip';
        $statusText = $isOnline ? 'Online' : 'Offline';
    ?>
    <div class="col-lg-4 col-md-6">
        <div class="data-card crane-list-card">
            <div class="crane-list-header">
                <div>
                    <h3 class="crane-list-name"><?php echo htmlspecialchars($crane['name']); ?></h3>
                    <span class="crane-list-location"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($crane['location'] ?: 'No location'); ?></span>
                </div>
                <span class="status-chip <?php echo $statusClass; ?>">
                    <span class="status-dot"></span> <?php echo $statusText; ?>
                </span>
            </div>
            <div class="crane-list-meta">
                <span><i class="bi bi-hash"></i> ID: <?php echo htmlspecialchars($crane['crane_id']); ?></span>
                <span><i class="bi bi-clock"></i> <?php echo $crane['last_data_at'] ? date('M d, H:i', $lastData) : 'No data yet'; ?></span>
            </div>
            <div class="crane-list-actions">
                <a href="crane_live.php?crane_id=<?php echo urlencode($crane['crane_id']); ?>" class="btn btn-primary-gradient btn-sm">
                    <i class="bi bi-display"></i> Live Dashboard
                </a>
                <a href="drives_live.php?crane_id=<?php echo urlencode($crane['crane_id']); ?>" class="btn btn-outline-action btn-sm">
                    <i class="bi bi-speedometer2"></i> Drives Data
                </a>
                <a href="reports.php?crane_id=<?php echo urlencode($crane['crane_id']); ?>" class="btn btn-outline-action btn-sm">
                    <i class="bi bi-file-bar-graph"></i> Reports
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
