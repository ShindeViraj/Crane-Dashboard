<?php
/**
 * Admin Users — User Management & Crane Assignment Dashboard
 * Accessible to: developer, admin
 */
require_once 'includes/auth.php';
requireRole(['developer', 'admin']);

$pageTitle = 'User Management';
$pdo = getDbConnection();
$currentUser = getCurrentUser();
$message = '';
$msgType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Change user role
    if ($action === 'change_role') {
        $targetId = intval($_POST['user_id'] ?? 0);
        $newRole = $_POST['new_role'] ?? '';
        
        // Fetch target user's current role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute([':id' => $targetId]);
        $target = $stmt->fetch();
        
        if ($targetId === $currentUser['id']) {
            $message = 'You cannot change your own role.';
            $msgType = 'error';
        } elseif (!$target) {
            $message = 'User not found.';
            $msgType = 'error';
        } elseif ($currentUser['role'] === 'admin' && $target['role'] === 'developer') {
            // Admin cannot alter a developer
            $message = 'Admins cannot modify developer accounts.';
            $msgType = 'error';
        } elseif ($currentUser['role'] === 'developer' && $target['role'] === 'admin') {
            // Developer cannot alter an admin
            $message = 'Developers cannot modify admin accounts.';
            $msgType = 'error';
        } elseif ($newRole === 'developer' && $currentUser['role'] !== 'developer') {
            $message = 'Only developers can promote users to developer role.';
            $msgType = 'error';
        } elseif ($newRole === 'admin' && $currentUser['role'] !== 'admin' && $currentUser['role'] !== 'developer') {
            $message = 'You do not have permission to set this role.';
            $msgType = 'error';
        } elseif (in_array($newRole, ['developer', 'admin', 'user'])) {
            $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->execute([':role' => $newRole, ':id' => $targetId]);
            $message = "User role updated to '$newRole' successfully.";
            $msgType = 'success';
        }
    }
    
    // Delete user
    if ($action === 'delete_user') {
        $targetId = intval($_POST['user_id'] ?? 0);
        
        // Fetch target user's current role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmt->execute([':id' => $targetId]);
        $target = $stmt->fetch();
        
        if ($targetId === $currentUser['id']) {
            $message = 'You cannot delete your own account.';
            $msgType = 'error';
        } elseif (!$target) {
            $message = 'User not found.';
            $msgType = 'error';
        } elseif ($currentUser['role'] === 'admin' && $target['role'] === 'developer') {
            $message = 'Admins cannot delete developer accounts.';
            $msgType = 'error';
        } elseif ($currentUser['role'] === 'developer' && $target['role'] === 'admin') {
            $message = 'Developers cannot delete admin accounts.';
            $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $targetId]);
            $message = 'User deleted successfully.';
            $msgType = 'success';
        }
    }
    
    // Assign cranes
    if ($action === 'save_assignments') {
        $targetId = intval($_POST['user_id'] ?? 0);
        $assignedCranes = $_POST['cranes'] ?? [];
        
        // Clear existing assignments
        $stmt = $pdo->prepare("DELETE FROM user_cranes WHERE user_id = :uid");
        $stmt->execute([':uid' => $targetId]);
        
        // Insert new
        if (!empty($assignedCranes)) {
            $insertStmt = $pdo->prepare("INSERT IGNORE INTO user_cranes (user_id, crane_id) VALUES (:uid, :cid)");
            foreach ($assignedCranes as $cid) {
                $insertStmt->execute([':uid' => $targetId, ':cid' => $cid]);
            }
        }
        
        $message = 'Crane assignments updated successfully (' . count($assignedCranes) . ' cranes assigned).';
        $msgType = 'success';
    }
}

// Fetch all users
$users = $pdo->query("SELECT id, username, email, display_name, role, created_at FROM users ORDER BY role ASC, username ASC")->fetchAll();

// Fetch all cranes
$allCranes = $pdo->query("SELECT crane_id, name, location FROM cranes ORDER BY crane_id ASC")->fetchAll();

// Fetch all assignments keyed by user_id
$assignmentRows = $pdo->query("SELECT user_id, crane_id FROM user_cranes")->fetchAll();
$assignments = [];
foreach ($assignmentRows as $row) {
    $assignments[$row['user_id']][] = $row['crane_id'];
}

// Selected user for assignment panel
$selectedUserId = intval($_GET['assign'] ?? $_POST['user_id'] ?? 0);

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item active">User Management</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title"><i class="bi bi-people"></i> User Management</h1>
</div>

<?php if ($message): ?>
<div class="row mb-3">
    <div class="col-12" style="padding-left:28px;padding-right:28px;">
        <div class="alert-custom <?php echo $msgType === 'success' ? 'alert-success-custom' : 'alert-error-custom'; ?>">
            <i class="bi <?php echo $msgType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="row mb-3">
    <div class="col-12" style="padding-left:28px;padding-right:28px;">
        <div class="alert-custom alert-error-custom">
            <i class="bi bi-exclamation-circle"></i>
            <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Users Table -->
    <div class="<?php echo $selectedUserId ? 'col-lg-7' : 'col-lg-12'; ?>">
        <div class="data-card">
            <h3 class="card-title text-uppercase"><i class="bi bi-person-lines-fill"></i> All Users (<?php echo count($users); ?>)</h3>
            <div class="table-responsive">
                <table class="table table-custom" id="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Cranes</th>
                            <th>Joined</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $assignedCount = count($assignments[$u['id']] ?? []);
                            $roleBadge = match($u['role']) {
                                'developer' => 'bg-danger',
                                'admin'     => 'bg-primary',
                                default     => 'bg-secondary',
                            };
                            $isSelf = ($u['id'] === $currentUser['id']);
                            // Mutual protection: admin can't touch developer, developer can't touch admin
                            $isProtected = (
                                ($currentUser['role'] === 'admin' && $u['role'] === 'developer') ||
                                ($currentUser['role'] === 'developer' && $u['role'] === 'admin')
                            );
                        ?>
                        <tr <?php echo $selectedUserId === $u['id'] ? 'style="background:rgba(0,33,71,0.06);"' : ''; ?>>
                            <td>
                                <div style="line-height:1.3;">
                                    <strong><?php echo htmlspecialchars($u['display_name']); ?></strong>
                                    <br><small style="color:#74777f;">@<?php echo htmlspecialchars($u['username']); ?></small>
                                    <?php if ($u['email']): ?>
                                    <br><small style="color:#74777f;"><?php echo htmlspecialchars($u['email']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge <?php echo $roleBadge; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                            <td>
                                <?php if ($u['role'] === 'user'): ?>
                                <span class="badge bg-dark"><?php echo $assignedCount; ?></span>
                                <?php else: ?>
                                <span class="badge bg-success" title="Full access">All</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <!-- Assign cranes button (only for 'user' role) -->
                                    <?php if ($u['role'] === 'user'): ?>
                                    <a href="admin_users.php?assign=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-action" title="Assign Cranes">
                                        <i class="bi bi-link-45deg"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$isSelf && !$isProtected): ?>
                                    <!-- Role change dropdown -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-action dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Change Role">
                                            <i class="bi bi-shield"></i>
                                        </button>
                                        <ul class="dropdown-menu" style="font-size:13px;">
                                            <?php if ($currentUser['role'] === 'developer'): ?>
                                            <li>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="new_role" value="developer">
                                                    <button type="submit" class="dropdown-item <?php echo $u['role']==='developer'?'active':''; ?>">Developer</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="new_role" value="admin">
                                                    <button type="submit" class="dropdown-item <?php echo $u['role']==='admin'?'active':''; ?>">Admin</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="new_role" value="user">
                                                    <button type="submit" class="dropdown-item <?php echo $u['role']==='user'?'active':''; ?>">User</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Delete -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user @<?php echo htmlspecialchars($u['username']); ?>? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger-outline" title="Delete User">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Crane Assignment Panel (shown when a user is selected) -->
    <?php if ($selectedUserId): 
        $selectedUser = null;
        foreach ($users as $u) { if ($u['id'] === $selectedUserId) { $selectedUser = $u; break; } }
        $userCranes = $assignments[$selectedUserId] ?? [];
    ?>
    <?php if ($selectedUser): ?>
    <div class="col-lg-5">
        <div class="data-card" style="border-left: 4px solid #002147;">
            <h3 class="card-title text-uppercase">
                <i class="bi bi-link-45deg"></i> Crane Assignments
            </h3>
            <div style="margin-bottom:16px; padding:12px; background:rgba(0,33,71,0.04); border-radius:8px;">
                <strong><?php echo htmlspecialchars($selectedUser['display_name']); ?></strong>
                <br><small style="color:#74777f;">@<?php echo htmlspecialchars($selectedUser['username']); ?> · <?php echo ucfirst($selectedUser['role']); ?></small>
            </div>
            
            <form method="POST" id="form-crane-assignments">
                <input type="hidden" name="action" value="save_assignments">
                <input type="hidden" name="user_id" value="<?php echo $selectedUserId; ?>">
                
                <div style="max-height:360px; overflow-y:auto;">
                    <?php foreach ($allCranes as $crane): 
                        $isAssigned = in_array($crane['crane_id'], $userCranes);
                    ?>
                    <div class="form-check" style="padding:10px 12px 10px 40px; border-bottom:1px solid rgba(0,0,0,0.06); margin:0;">
                        <input class="form-check-input" type="checkbox" name="cranes[]" 
                               value="<?php echo htmlspecialchars($crane['crane_id']); ?>" 
                               id="crane-<?php echo htmlspecialchars($crane['crane_id']); ?>"
                               <?php echo $isAssigned ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="crane-<?php echo htmlspecialchars($crane['crane_id']); ?>" style="cursor:pointer; width:100%;">
                            <strong><?php echo htmlspecialchars($crane['name']); ?></strong>
                            <span style="color:#74777f; font-size:12px;"> · ID: <?php echo htmlspecialchars($crane['crane_id']); ?></span>
                            <?php if ($crane['location']): ?>
                            <br><small style="color:#74777f;"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($crane['location']); ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($allCranes)): ?>
                    <p class="text-muted text-center" style="padding:20px;">No cranes configured yet.</p>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary-gradient flex-fill">
                        <i class="bi bi-check-lg"></i> Save Assignments
                    </button>
                    <a href="admin_users.php" class="btn btn-outline-action">
                        <i class="bi bi-x-lg"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
