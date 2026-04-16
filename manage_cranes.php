<?php
/**
 * Manage Cranes — Add, Edit, Delete cranes
 */
require_once 'includes/auth.php';
requireLogin();

$pageTitle = 'Manage Cranes';
$pdo = getDbConnection();
$message = '';
$msgType = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $craneId = trim($_POST['crane_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($craneId) || empty($name)) {
            $message = 'Crane ID and Name are required.';
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO cranes (crane_id, name, location, description) VALUES (:cid, :name, :loc, :desc)");
                $stmt->execute([':cid' => $craneId, ':name' => $name, ':loc' => $location, ':desc' => $description]);
                $message = "Crane '$name' (ID: $craneId) added successfully!";
                $msgType = 'success';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $message = "Crane ID '$craneId' already exists.";
                } else {
                    $message = 'Error: ' . $e->getMessage();
                }
                $msgType = 'error';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE cranes SET name = :name, location = :loc, description = :desc WHERE id = :id");
            $stmt->execute([':name' => $name, ':loc' => $location, ':desc' => $description, ':id' => $id]);
            $message = "Crane updated successfully!";
            $msgType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM cranes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $message = "Crane deleted successfully.";
            $msgType = 'success';
        }
    }
}

// Fetch all cranes with dynamic online status timestamp
$cranes = $pdo->query("SELECT c.*, (SELECT MAX(cd.Timestamp) FROM crane_data cd WHERE cd.crane_id = c.crane_id) as last_data_at FROM cranes c ORDER BY c.crane_id ASC")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<nav aria-label="breadcrumb" class="page-breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="breadcrumb-item active">Manage Cranes</li>
    </ol>
</nav>

<div class="page-header">
    <h1 class="page-title">Manage Cranes</h1>
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

<div class="row g-4 mb-4">
    <!-- Add Crane Form -->
    <div class="col-lg-5">
        <div class="data-card">
            <h3 class="card-title text-uppercase"><i class="bi bi-plus-circle"></i> Add New Crane</h3>
            <form method="POST" id="form-add-crane">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="settings-label" for="add-crane-id">Crane ID *</label>
                    <input type="text" class="form-control form-input-custom" id="add-crane-id" name="crane_id" 
                           placeholder="e.g., 1, 2, CR-001" required>
                    <small class="form-text text-muted">This ID must match the crane_id sent from Node-RED.</small>
                </div>
                <div class="mb-3">
                    <label class="settings-label" for="add-crane-name">Crane Name *</label>
                    <input type="text" class="form-control form-input-custom" id="add-crane-name" name="name" 
                           placeholder="e.g., Crane 1, HOT Crane A" required>
                </div>
                <div class="mb-3">
                    <label class="settings-label" for="add-crane-location">Location</label>
                    <input type="text" class="form-control form-input-custom" id="add-crane-location" name="location" 
                           placeholder="e.g., Bay 3, SA3">
                </div>
                <div class="mb-3">
                    <label class="settings-label" for="add-crane-desc">Description</label>
                    <textarea class="form-control form-input-custom" id="add-crane-desc" name="description" rows="2"
                              placeholder="Optional notes about this crane"></textarea>
                </div>
                <button type="submit" class="btn btn-success-gradient" id="btn-add-crane">
                    <i class="bi bi-plus-lg"></i> Add Crane
                </button>
            </form>
        </div>
    </div>
    
    <!-- Existing Cranes Table -->
    <div class="col-lg-7">
        <div class="data-card">
            <h3 class="card-title text-uppercase"><i class="bi bi-list-columns-reverse"></i> Existing Cranes (<?php echo count($cranes); ?>)</h3>
            
            <?php if (empty($cranes)): ?>
            <p class="text-muted text-center" style="padding:30px;">No cranes configured yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-custom" id="cranes-table">
                    <thead>
                        <tr>
                            <th>Crane ID</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th style="width:120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cranes as $crane): 
                            $lastData = $crane['last_data_at'] ? strtotime($crane['last_data_at']) : 0;
                            $isOnline = (time() - $lastData) < 50;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($crane['crane_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($crane['name']); ?></td>
                            <td><?php echo htmlspecialchars($crane['location'] ?: '—'); ?></td>
                            <td>
                                <span class="status-chip <?php echo $isOnline ? 'status-online' : 'status-idle-chip'; ?>">
                                    <span class="status-dot"></span> <?php echo $isOnline ? 'Online' : 'Offline'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-action" onclick="editCrane(<?php echo htmlspecialchars(json_encode($crane)); ?>)" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this crane? Data will NOT be deleted.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $crane['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger-outline" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCraneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <form method="POST" id="form-edit-crane">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header" style="border:none;padding:24px 24px 12px;">
                    <h5 class="modal-title" style="font-family:'Manrope';font-weight:700;">Edit Crane</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:12px 24px 24px;">
                    <div class="mb-3">
                        <label class="settings-label">Crane ID</label>
                        <input type="text" class="form-control form-input-custom" id="edit-crane-id" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="settings-label" for="edit-name">Name</label>
                        <input type="text" class="form-control form-input-custom" id="edit-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="settings-label" for="edit-location">Location</label>
                        <input type="text" class="form-control form-input-custom" id="edit-location" name="location">
                    </div>
                    <div class="mb-3">
                        <label class="settings-label" for="edit-description">Description</label>
                        <textarea class="form-control form-input-custom" id="edit-description" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border:none;padding:0 24px 24px;">
                    <button type="button" class="btn btn-outline-action" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCrane(crane) {
    document.getElementById('edit-id').value = crane.id;
    document.getElementById('edit-crane-id').value = crane.crane_id;
    document.getElementById('edit-name').value = crane.name;
    document.getElementById('edit-location').value = crane.location || '';
    document.getElementById('edit-description').value = crane.description || '';
    new bootstrap.Modal(document.getElementById('editCraneModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
