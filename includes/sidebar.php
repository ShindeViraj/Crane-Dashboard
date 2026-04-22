<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Logo Area -->
    <div class="sidebar-logo">
        <div class="logo-img-wrap">
            <img src="assets/logo.png" alt="SquareWave Logo" class="sidebar-logo-img">
        </div>
        <div class="logo-text">
            <span class="logo-brand">SQUAREWAVE</span>
            <span class="logo-sub">AUTOMATION TECHNOLOGIES PVT. LTD.</span>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">Navigation</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" id="nav-dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'cranes.php' ? 'active' : ''; ?>" href="cranes.php" id="nav-cranes">
                    <i class="bi bi-list-ul"></i>
                    <span>View All Cranes</span>
                </a>
            </li>
        </ul>
        
        <?php $__sidebarUser = getCurrentUser(); ?>
        
        <!-- Admin/Developer Management Section -->
        <?php if ($__sidebarUser && in_array($__sidebarUser['role'], ['developer', 'admin'])): ?>
        <div class="nav-section-label" style="margin-top:16px;">Management</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_cranes.php' ? 'active' : ''; ?>" href="manage_cranes.php" id="nav-manage">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>Manage Cranes</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'admin_users.php' ? 'active' : ''; ?>" href="admin_users.php" id="nav-admin-users">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- Reports Section (visible to all) -->
        <div class="nav-section-label" style="margin-top:16px;">Reports</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>" href="reports.php" id="nav-reports">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    <span>Telemetry Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'fault_reports.php' ? 'active' : ''; ?>" href="fault_reports.php" id="nav-fault-reports">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Fault History</span>
                </a>
            </li>
        </ul>
        
        <!-- Settings (visible to all) -->
        <div class="nav-section-label" style="margin-top:16px;">Account</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>" href="settings.php" id="nav-settings">
                    <i class="bi bi-sliders"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <?php $currentUser = getCurrentUser(); ?>
        <div class="sidebar-user">
            <div class="user-avatar">
                <span><?php echo $currentUser ? strtoupper(substr($currentUser['display_name'],0,1) . substr($currentUser['display_name'], strpos($currentUser['display_name'],' ')+1, 1)) : 'AU'; ?></span>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($currentUser['display_name'] ?? 'Admin User'); ?></span>
                <span class="user-role"><?php echo ucfirst($currentUser['role'] ?? 'user'); ?></span>
            </div>
            <a href="logout.php" class="sidebar-logout-btn" title="Logout" id="btn-logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Main Content Wrapper -->
<main class="main-content" id="main-content">
    <!-- Top Bar -->
    <div class="topbar">
        <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-right">
            <div class="live-indicator" id="live-indicator">
                <span class="live-dot"></span>
                <span class="live-text">LIVE</span>
            </div>
            <span class="topbar-time" id="topbar-time"></span>
            <a href="logout.php" class="topbar-logout" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
