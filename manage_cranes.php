<?php
/**
 * Manage Cranes — Add, Edit, Delete cranes
 */
require_once 'includes/auth.php';
requireRole(['developer', 'admin']);
require_once 'includes/dividers.php';

$pageTitle = 'Manage Cranes';
$pdo = getDbConnection();
$message = '';
$msgType = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check — fail closed
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Session expired. Please refresh the page and try again.';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $craneId = trim($_POST['crane_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $capacity = trim($_POST['capacity'] ?? '');
            $totalLifeHours = isset($_POST['total_life_hours']) && $_POST['total_life_hours'] !== '' ? floatval($_POST['total_life_hours']) : null;
            $description = trim($_POST['description'] ?? '');
            $dividers = parseDividersFromForm($_POST);
            
            if (empty($craneId) || empty($name)) {
                $message = 'Crane ID and Name are required.';
                $msgType = 'error';
            } else {
                try {
                    $divJson = !empty($dividers) ? json_encode($dividers) : null;
                    $stmt = $pdo->prepare("INSERT INTO cranes (crane_id, name, capacity, total_life_hours, location, description, dividers) VALUES (:cid, :name, :cap, :tlh, :loc, :desc, :div)");
                    $stmt->execute([':cid' => $craneId, ':name' => $name, ':cap' => $capacity, ':tlh' => $totalLifeHours, ':loc' => $location, ':desc' => $description, ':div' => $divJson]);
                    
                    // Sync dividers to cache file
                    syncDividersCache($craneId, $dividers);
                    
                    $message = "Crane '$name' (ID: $craneId) added successfully!";
                    $msgType = 'success';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $message = "Crane ID '$craneId' already exists.";
                    } else {
                        $message = 'Failed to add crane. Please try again.';
                    }
                    $msgType = 'error';
                }
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $newCraneId = trim($_POST['crane_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $capacity = trim($_POST['capacity'] ?? '');
            $totalLifeHours = isset($_POST['total_life_hours']) && $_POST['total_life_hours'] !== '' ? floatval($_POST['total_life_hours']) : null;
            $description = trim($_POST['description'] ?? '');
            $dividers = parseDividersFromForm($_POST);
            
            if ($id && $name && $newCraneId) {
                try {
                    $pdo->beginTransaction();
                    
                    // Get old crane_id and dividers
                    $stmt = $pdo->prepare("SELECT crane_id, dividers FROM cranes WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $oldCrane = $stmt->fetch();
                    $oldCraneId = $oldCrane['crane_id'];
                    $oldDividers = !empty($oldCrane['dividers']) ? json_decode($oldCrane['dividers'], true) : [];
                    
                    $divJson = !empty($dividers) ? json_encode($dividers) : null;
                    
                    if ($oldCraneId !== $newCraneId) {
                        // Check if new crane_id exists
                        $stmt = $pdo->prepare("SELECT id FROM cranes WHERE crane_id = :cid");
                        $stmt->execute([':cid' => $newCraneId]);
                        if ($stmt->fetch()) {
                            throw new Exception("New Crane ID '$newCraneId' already exists.");
                        }
                        
                        // Temporarily disable FK checks to allow update
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                        
                        // Update cranes
                        $stmt = $pdo->prepare("UPDATE cranes SET crane_id = :new_cid, name = :name, capacity = :cap, total_life_hours = :tlh, location = :loc, description = :desc, dividers = :div WHERE id = :id");
                        $stmt->execute([':new_cid' => $newCraneId, ':name' => $name, ':cap' => $capacity, ':tlh' => $totalLifeHours, ':loc' => $location, ':desc' => $description, ':div' => $divJson, ':id' => $id]);
                        
                        // Update related tables to preserve history and assignments
                        $stmt = $pdo->prepare("UPDATE user_cranes SET crane_id = :new_cid WHERE crane_id = :old_cid");
                        $stmt->execute([':new_cid' => $newCraneId, ':old_cid' => $oldCraneId]);
                        
                        $stmt = $pdo->prepare("UPDATE crane_data SET crane_id = :new_cid WHERE crane_id = :old_cid");
                        $stmt->execute([':new_cid' => $newCraneId, ':old_cid' => $oldCraneId]);
                        
                        // Re-enable FK checks
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                        
                        // Delete old cache, create new
                        $oldCacheFile = getDividersCacheFile($oldCraneId);
                        if (file_exists($oldCacheFile)) @unlink($oldCacheFile);
                        syncDividersCache($newCraneId, $dividers);
                    } else {
                        // No crane_id change, just update other fields
                        $stmt = $pdo->prepare("UPDATE cranes SET name = :name, capacity = :cap, total_life_hours = :tlh, location = :loc, description = :desc, dividers = :div WHERE id = :id");
                        $stmt->execute([':name' => $name, ':cap' => $capacity, ':tlh' => $totalLifeHours, ':loc' => $location, ':desc' => $description, ':div' => $divJson, ':id' => $id]);
                        
                        syncDividersCache($newCraneId, $dividers);
                    }
                    
                    // Retroactive divider update for historical data
                    if (!empty($_POST['retroactive_update'])) {
                        $setClauses = [];
                        foreach (MOTION_PREFIXES as $prefix) {
                            foreach (DIVISIBLE_PARAMS as $param) {
                                $col = $prefix . '_' . $param;
                                $oldDiv = isset($oldDividers[$col]) ? (float)$oldDividers[$col] : 1.0;
                                $newDiv = isset($dividers[$col]) ? (float)$dividers[$col] : 1.0;
                                
                                if ($oldDiv <= 0) $oldDiv = 1.0;
                                if ($newDiv <= 0) $newDiv = 1.0;
                                
                                if (abs($oldDiv - $newDiv) > 0.0001) {
                                    $multiplier = $oldDiv / $newDiv;
                                    // For integer-based cols like Logic_input
                                    if (strpos($col, 'Logic_input') !== false || strpos($col, 'Logic_output') !== false) {
                                        $setClauses[] = "$col = ROUND($col * $multiplier)";
                                    } else {
                                        $setClauses[] = "$col = ROUND($col * $multiplier, 4)";
                                    }
                                }
                            }
                        }
                        
                        if (!empty($setClauses)) {
                            // Single massive update query to avoid hitting the 500 queries/hour limit
                            $sql = "UPDATE crane_data SET " . implode(', ', $setClauses) . " WHERE crane_id = :cid";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([':cid' => $newCraneId]);
                        }
                    }
                    
                    $pdo->commit();
                    $message = "Crane updated successfully! (Submitted Life: " . (isset($_POST['total_life_hours']) && $_POST['total_life_hours'] !== '' ? htmlspecialchars($_POST['total_life_hours']) : 'empty/not set') . ")";
                    $msgType = 'success';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                    }
                    $message = $e->getMessage();
                    if (strpos($message, 'Duplicate') !== false) {
                        $message = "Crane ID '$newCraneId' already exists.";
                    } elseif (strpos($message, 'already exists') === false) {
                        $message = 'Failed to update crane. ' . $message;
                    }
                    $msgType = 'error';
                }
            } else {
                $message = 'Crane ID and Name are required.';
                $msgType = 'error';
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                // Get crane_id before deleting to clean up cache
                $stmt = $pdo->prepare("SELECT crane_id FROM cranes WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $delCraneId = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("DELETE FROM cranes WHERE id = :id");
                $stmt->execute([':id' => $id]);
                
                // Clean up cache file
                if ($delCraneId) {
                    $cacheFile = getDividersCacheFile($delCraneId);
                    if (file_exists($cacheFile)) @unlink($cacheFile);
                }
                
                $message = "Crane deleted successfully.";
                $msgType = 'success';
            }
        }
    }
}

// Fetch all cranes with dynamic online status timestamp
$cranes = $pdo->query("SELECT c.*, (SELECT MAX(cd.Timestamp) FROM crane_data cd WHERE cd.crane_id = c.crane_id) as last_data_at FROM cranes c ORDER BY c.crane_id ASC")->fetchAll();

// Prepare the divider parameter labels for the UI grid
$paramLabels = [
    'Drive_status' => 'Drive Status',
    'Output_frequency' => 'Output Freq',
    'Motor_current' => 'Motor Current',
    'Motor_torque' => 'Motor Torque',
    'Mains_voltage' => 'Mains Voltage',
    'Motor_voltage' => 'Motor Voltage',
    'Motor_power' => 'Motor Power',
    'Drive_temp' => 'Drive Temp',
    'Motion_run_time' => 'Run Time',
    'Logic_input' => 'Logic Input',
    'Logic_output' => 'Logic Output',
    'Encoder' => 'Encoder',
    'Load_data' => 'Load Data',
    'di' => 'DI'
];

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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
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
                    <label class="settings-label" for="add-crane-capacity">Capacity</label>
                    <input type="text" class="form-control form-input-custom" id="add-crane-capacity" name="capacity" 
                           placeholder="e.g., 10 Ton, 25 MT">
                </div>
                <div class="mb-3">
                    <label class="settings-label" for="add-crane-life">Total Life (Hours)</label>
                    <input type="number" step="0.1" class="form-control form-input-custom" id="add-crane-life" name="total_life_hours" placeholder="e.g., 50000">
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
                
                <!-- Dividers Section (Collapsible) -->
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-action w-100" type="button" data-bs-toggle="collapse" data-bs-target="#add-dividers-section">
                        <i class="bi bi-sliders"></i> Parameter Dividers <small class="text-muted">(optional — default: 1)</small>
                    </button>
                    <div class="collapse mt-2" id="add-dividers-section">
                        <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                            <table class="table table-sm table-bordered" style="font-size:0.8rem;">
                                <thead style="position:sticky;top:0;z-index:1;background:var(--bg-card);">
                                    <tr>
                                        <th style="min-width:100px;">Parameter</th>
                                        <?php foreach (MOTION_PREFIXES as $prefix): ?>
                                        <th class="text-center" style="width:70px;"><?php echo $prefix; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paramLabels as $paramKey => $paramLabel): ?>
                                    <tr>
                                        <td class="align-middle" style="font-weight:600;font-size:0.75rem;"><?php echo $paramLabel; ?></td>
                                        <?php foreach (MOTION_PREFIXES as $prefix): ?>
                                        <td>
                                            <input type="number" step="any" min="0" 
                                                   class="form-control form-control-sm text-center" 
                                                   name="divider[<?php echo $prefix . '_' . $paramKey; ?>]" 
                                                   placeholder="1" 
                                                   style="font-size:0.75rem;padding:2px 4px;">
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <small class="form-text text-muted">Leave empty or set to 1 for no scaling. Incoming value will be divided by this number.</small>
                    </div>
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
                            <th>SO. NO.</th>
                            <th>Name</th>
                            <th>Capacity</th>
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
                            <td><?php echo htmlspecialchars($crane['capacity'] ?: '—'); ?></td>
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
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <form method="POST" id="form-edit-crane">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-header" style="border:none;padding:24px 24px 12px;">
                    <h5 class="modal-title" style="font-family:'Manrope';font-weight:700;">Edit Crane</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:12px 24px 24px;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="settings-label" for="edit-crane-id">Crane ID *</label>
                            <input type="text" class="form-control form-input-custom" id="edit-crane-id" name="crane_id" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="settings-label" for="edit-name">Name *</label>
                            <input type="text" class="form-control form-input-custom" id="edit-name" name="name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="settings-label" for="edit-capacity">Capacity</label>
                            <input type="text" class="form-control form-input-custom" id="edit-capacity" name="capacity" placeholder="e.g., 10 Ton, 25 MT">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="settings-label" for="edit-total-life">Total Life (Hours)</label>
                            <input type="number" step="0.1" class="form-control form-input-custom" id="edit-total-life" name="total_life_hours" placeholder="e.g., 50000">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="settings-label" for="edit-location">Location</label>
                            <input type="text" class="form-control form-input-custom" id="edit-location" name="location">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="settings-label" for="edit-description">Description</label>
                            <textarea class="form-control form-input-custom" id="edit-description" name="description" rows="1"></textarea>
                        </div>
                    </div>
                    
                    <!-- Dividers Grid -->
                    <hr style="border-color:var(--border-color);margin:8px 0 16px;">
                    <h6 style="font-family:'Manrope';font-weight:700;margin-bottom:12px;">
                        <i class="bi bi-sliders"></i> Parameter Dividers
                        <small class="text-muted fw-normal"> — leave empty or 1 for no scaling</small>
                    </h6>
                    <div class="table-responsive" style="max-height:350px;overflow-y:auto;">
                        <table class="table table-sm table-bordered mb-0" style="font-size:0.8rem;">
                            <thead style="position:sticky;top:0;z-index:1;background:var(--bg-card);">
                                <tr>
                                    <th style="min-width:100px;">Parameter</th>
                                    <?php foreach (MOTION_PREFIXES as $prefix): ?>
                                    <th class="text-center" style="width:70px;"><?php echo $prefix; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paramLabels as $paramKey => $paramLabel): ?>
                                <tr>
                                    <td class="align-middle" style="font-weight:600;font-size:0.75rem;"><?php echo $paramLabel; ?></td>
                                    <?php foreach (MOTION_PREFIXES as $prefix): 
                                        $fieldName = $prefix . '_' . $paramKey;
                                    ?>
                                    <td>
                                        <input type="number" step="any" min="0" 
                                               class="form-control form-control-sm text-center edit-divider" 
                                               name="divider[<?php echo $fieldName; ?>]" 
                                               id="edit-div-<?php echo $fieldName; ?>"
                                               placeholder="1" 
                                               style="font-size:0.75rem;padding:2px 4px;">
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 form-check" style="background: rgba(230, 126, 34, 0.1); padding: 12px 12px 12px 32px; border-radius: 6px; border: 1px solid rgba(230, 126, 34, 0.3);">
                        <input class="form-check-input" type="checkbox" name="retroactive_update" value="1" id="retroactive_update">
                        <label class="form-check-label" for="retroactive_update" style="font-size: 0.85rem; font-weight: 600; color: #d35400; cursor: pointer;">
                            Apply new dividers retroactively to all past historical data
                        </label>
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
// Divider field names for populating the edit modal
const dividerFields = [
    <?php 
    $fields = [];
    foreach (MOTION_PREFIXES as $prefix) {
        foreach (array_keys($paramLabels) as $paramKey) {
            $fields[] = "'" . $prefix . '_' . $paramKey . "'";
        }
    }
    echo implode(',', $fields);
    ?>
];

function editCrane(crane) {
    document.getElementById('edit-id').value = crane.id;
    document.getElementById('edit-crane-id').value = crane.crane_id;
    document.getElementById('edit-name').value = crane.name;
    document.getElementById('edit-capacity').value = crane.capacity || '';
    document.getElementById('edit-location').value = crane.location || '';
    document.getElementById('edit-description').value = crane.description || '';
    document.getElementById('edit-total-life').value = crane.total_life_hours || '';
    
    // Parse dividers JSON from database
    let dividers = {};
    if (crane.dividers) {
        try {
            dividers = typeof crane.dividers === 'string' ? JSON.parse(crane.dividers) : crane.dividers;
        } catch(e) {
            dividers = {};
        }
    }
    
    // Populate all divider fields
    dividerFields.forEach(function(field) {
        let input = document.getElementById('edit-div-' + field);
        if (input) {
            input.value = (dividers[field] && dividers[field] != 1) ? dividers[field] : '';
        }
    });
    
    new bootstrap.Modal(document.getElementById('editCraneModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
