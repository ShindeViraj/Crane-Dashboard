<?php
/**
 * Settings — Profile & Password Management
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Settings';
$user = getCurrentUser();
$profileMsg = '';
$profileMsgType = '';
$passwordMsg = '';
$passwordMsgType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'profile') {
    $displayName = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    
    if (empty($displayName) || empty($username)) {
        $profileMsg = 'Display name and username are required.';
        $profileMsgType = 'error';
    } else {
        $result = updateProfile($user['id'], $displayName, $email, $username);
        $profileMsg = $result['success'] ? $result['message'] : $result['error'];
        $profileMsgType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            $user = getCurrentUser(); // Refresh user data
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        $passwordMsg = 'All password fields are required.';
        $passwordMsgType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordMsg = 'New passwords do not match.';
        $passwordMsgType = 'error';
    } else {
        $result = changePassword($user['id'], $currentPassword, $newPassword);
        $passwordMsg = $result['success'] ? $result['message'] : $result['error'];
        $passwordMsgType = $result['success'] ? 'success' : 'error';
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item active">Settings</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title">Settings</h1>
</div>

<!-- Settings Tabs -->
<div class="row mb-4" style="padding:0 28px;">
    <div class="col-12">
        <ul class="nav nav-tabs settings-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-pane" type="button" role="tab">
                    <i class="bi bi-person"></i> Profile
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-pane" type="button" role="tab">
                    <i class="bi bi-lock"></i> Password
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api-pane" type="button" role="tab">
                    <i class="bi bi-plug"></i> API Info
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content" id="settingsTabContent">
    <!-- Profile Tab -->
    <div class="tab-pane fade show active" id="profile-pane" role="tabpanel">
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="data-card">
                    <h3 class="card-title text-uppercase">Profile Information</h3>
                    
                    <?php if ($profileMsg): ?>
                    <div class="alert-custom <?php echo $profileMsgType === 'success' ? 'alert-success-custom' : 'alert-error-custom'; ?> mb-3">
                        <i class="bi <?php echo $profileMsgType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($profileMsg); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="form-profile">
                        <input type="hidden" name="form_type" value="profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="settings-label" for="display_name">Display Name</label>
                                <input type="text" class="form-control form-input-custom" id="display_name" name="display_name" 
                                       value="<?php echo htmlspecialchars($user['display_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="settings-label" for="username">Username</label>
                                <input type="text" class="form-control form-input-custom" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="settings-label" for="email">Email Address</label>
                                <input type="email" class="form-control form-input-custom" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="admin@company.com">
                            </div>
                            <div class="col-md-6">
                                <label class="settings-label">Role</label>
                                <input type="text" class="form-control form-input-custom" value="<?php echo ucfirst($user['role']); ?>" disabled>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary-gradient" id="btn-save-profile">
                                <i class="bi bi-check-lg"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Password Tab -->
    <div class="tab-pane fade" id="password-pane" role="tabpanel">
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="data-card">
                    <h3 class="card-title text-uppercase">Change Password</h3>
                    
                    <?php if ($passwordMsg): ?>
                    <div class="alert-custom <?php echo $passwordMsgType === 'success' ? 'alert-success-custom' : 'alert-error-custom'; ?> mb-3">
                        <i class="bi <?php echo $passwordMsgType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($passwordMsg); ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="form-password">
                        <input type="hidden" name="form_type" value="password">
                        <div class="mb-3">
                            <label class="settings-label" for="current_password">Current Password</label>
                            <input type="password" class="form-control form-input-custom" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="settings-label" for="new_password">New Password</label>
                            <input type="password" class="form-control form-input-custom" id="new_password" name="new_password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="settings-label" for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control form-input-custom" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary-gradient" id="btn-change-password">
                            <i class="bi bi-lock"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- API Info Tab -->
    <div class="tab-pane fade" id="api-pane" role="tabpanel">
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="data-card">
                    <h3 class="card-title text-uppercase">API Endpoints for Node-RED</h3>
                    <p style="font-size:13px;color:#44474e;margin-bottom:20px;">Use these URLs in your Node-RED HTTP Request nodes.</p>
                    
                    <div class="api-info-block">
                        <h4 class="api-info-title">Live Data Ingestion (Single Record)</h4>
                        <div class="api-url-box">
                            <code id="api-url-live">POST <?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/api/receive_data.php</code>
                            <button class="btn btn-sm btn-outline-action" onclick="copyUrl('api-url-live')"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <small>Content-Type: application/json • Accepts single JSON object with VFD parameters</small>
                    </div>
                    
                    <div class="api-info-block mt-3">
                        <h4 class="api-info-title">Offline Bulk Sync (Raspberry Pi Dump)</h4>
                        <div class="api-url-box">
                            <code id="api-url-bulk">POST <?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/api/bulk_insert.php</code>
                            <button class="btn btn-sm btn-outline-action" onclick="copyUrl('api-url-bulk')"><i class="bi bi-clipboard"></i></button>
                        </div>
                        <small>Content-Type: application/json • Accepts JSON array of records from SELECT *</small>
                    </div>
                    
                    <div class="api-info-block mt-3">
                        <h4 class="api-info-title">Sample JSON Payload</h4>
                        <pre class="api-code-block"><code>{
  "Timestamp": "2026-04-16 12:00:23",
  "crane_id": "1",
  "MH_Drive_status": "66",
  "MH_Output_frequency": 73,
  "MH_Motor_current": 89,
  ...
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyUrl(id) {
    const text = document.getElementById(id).textContent.replace(/^(POST|GET) /, '');
    navigator.clipboard.writeText(text).then(() => {
        alert('URL copied to clipboard!');
    });
}

// If password tab had errors, show it
<?php if ($passwordMsg && $passwordMsgType === 'error'): ?>
document.getElementById('password-tab').click();
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
