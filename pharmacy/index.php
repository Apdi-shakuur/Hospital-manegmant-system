<?php
/**
 * Pharmacy Inventory & Stock Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'Pharmacy Inventory';
$activeNav = 'pharmacy';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Pharmacist']);

$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Handle Add Medicine / Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create_medicine') {
        $name     = sanitize($_POST['name'] ?? '');
        $generic  = sanitize($_POST['generic_name'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $mfg      = sanitize($_POST['manufacturer'] ?? '');
        $batch    = sanitize($_POST['batch_number'] ?? '');
        $expiry   = $_POST['expiry_date'] ?? '';
        $qty      = (int)($_POST['quantity'] ?? 0);
        $price    = (float)($_POST['unit_price'] ?? 0.00);
        $reorder  = (int)($_POST['reorder_level'] ?? 10);
        $supplier = sanitize($_POST['supplier_name'] ?? '');

        if (empty($name) || empty($batch) || empty($expiry) || $price <= 0) {
            $message = 'Drug Name, Batch Number, Expiry Date, and Unit Price are required.';
            $messageType = 'danger';
        } else {
            try {
                $code = generate_code('MED');
                $stmt = $db->prepare("
                    INSERT INTO medicines (medicine_code, name, generic_name, category, manufacturer, batch_number, expiry_date, quantity, unit_price, reorder_level, supplier_name, status) 
                    VALUES (:code, :name, :gen, :cat, :mfg, :batch, :exp, :qty, :price, :reorder, :supplier, 'Active')
                ");
                $stmt->execute([
                    ':code'     => $code,
                    ':name'     => $name,
                    ':gen'      => $generic,
                    ':cat'      => $category,
                    ':mfg'      => $mfg,
                    ':batch'    => $batch,
                    ':exp'      => $expiry,
                    ':qty'      => $qty,
                    ':price'    => $price,
                    ':reorder'  => $reorder,
                    ':supplier' => $supplier
                ]);
                $medId = (int)$db->lastInsertId();

                // Stock In Log
                $logStmt = $db->prepare("INSERT INTO medicine_stock_log (medicine_id, transaction_type, quantity, notes, created_by) VALUES (:mid, 'STOCK_IN', :qty, 'Initial stock entry', :uid)");
                $logStmt->execute([':mid' => $medId, ':qty' => $qty, ':uid' => current_user()['id']]);

                log_activity($db, current_user()['id'], 'Medicine Added', "Added medicine '{$name}' ({$code}) into pharmacy inventory.");
                $message = "Medicine '{$name}' ({$code}) added to inventory successfully.";
            } catch (Exception $e) {
                error_log("Med Add Error: " . $e->getMessage());
                $message = 'Error adding medicine to inventory.';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'adjust_stock') {
        $medId = (int)($_POST['medicine_id'] ?? 0);
        $type  = $_POST['transaction_type'] ?? 'STOCK_IN';
        $adjQty = (int)($_POST['adjust_quantity'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');

        if ($medId <= 0 || $adjQty <= 0) {
            $message = 'Valid medicine and positive quantity required for stock adjustment.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                if ($type === 'STOCK_IN') {
                    $uStmt = $db->prepare("UPDATE medicines SET quantity = quantity + :qty WHERE id = :id");
                } else {
                    $uStmt = $db->prepare("UPDATE medicines SET quantity = GREATEST(0, quantity - :qty) WHERE id = :id");
                }
                $uStmt->execute([':qty' => $adjQty, ':id' => $medId]);

                $logStmt = $db->prepare("INSERT INTO medicine_stock_log (medicine_id, transaction_type, quantity, notes, created_by) VALUES (:mid, :type, :qty, :notes, :uid)");
                $logStmt->execute([':mid' => $medId, ':type' => $type, ':qty' => $adjQty, ':notes' => $notes, ':uid' => current_user()['id']]);

                $db->commit();
                log_activity($db, current_user()['id'], 'Stock Adjusted', "Adjusted stock ({$type} {$adjQty}) for Medicine ID {$medId}.");
                $message = "Stock adjusted successfully.";
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error performing stock adjustment.';
                $messageType = 'danger';
            }
        }
    }
}

// Search and Filter
$searchQuery = sanitize($_GET['search'] ?? '');
$filterType  = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM medicines WHERE status = 'Active'";
if (!empty($searchQuery)) {
    $sql .= " AND (medicine_code LIKE :q OR name LIKE :q OR generic_name LIKE :q OR category LIKE :q)";
}

if ($filterType === 'low_stock') {
    $sql .= " AND quantity <= reorder_level";
} elseif ($filterType === 'expired') {
    $sql .= " AND expiry_date <= CURRENT_DATE()";
}

$sql .= " ORDER BY name ASC";

$stmt = $db->prepare($sql);
if (!empty($searchQuery)) {
    $stmt->execute([':q' => "%{$searchQuery}%"]);
} else {
    $stmt->execute();
}
$medicines = $stmt->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Pharmacy Stock Management</h1>
                <div class="page-subtitle">Track drug inventory, batch numbers, reorder thresholds, and expiries</div>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <a href="dispensing.php" class="btn btn-success">Dispense Prescriptions &rarr;</a>
                <button class="btn btn-primary" onclick="openModal('add-med-modal')">+ Add Medicine</button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem;">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <!-- Search and Filter Bar -->
        <div class="card">
            <form method="GET" action="index.php" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
                <div class="search-box" style="flex:1; min-width:260px;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search drug name, code, category..." value="<?= escape($searchQuery) ?>">
                </div>

                <div style="width:180px;">
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Medicines</option>
                        <option value="low_stock" <?= $filterType === 'low_stock' ? 'selected' : '' ?>>⚠️ Low Stock Items</option>
                        <option value="expired" <?= $filterType === 'expired' ? 'selected' : '' ?>>⛔ Expired Items</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>

        <!-- Medicines Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Medicine / Generic Name</th>
                            <th>Category</th>
                            <th>Batch & Expiry</th>
                            <th>Current Stock</th>
                            <th>Unit Price</th>
                            <th>Reorder Level</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--slate-400); padding:2rem;">No medicines found in pharmacy inventory.</td></tr>
                        <?php else: ?>
                            <?php foreach ($medicines as $m): ?>
                                <?php
                                $isLow = $m['quantity'] <= $m['reorder_level'];
                                $isExpired = strtotime($m['expiry_date']) <= time();
                                ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($m['medicine_code']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($m['name']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($m['generic_name']) ?></div>
                                    </td>
                                    <td><span class="badge badge-info"><?= escape($m['category']) ?></span></td>
                                    <td>
                                        <div>Batch: <code><?= escape($m['batch_number']) ?></code></div>
                                        <div style="font-size:0.75rem; color:<?= $isExpired ? 'var(--rose-600)' : 'var(--slate-400)' ?>; font-weight:<?= $isExpired ? '700' : '400' ?>;">
                                            Exp: <?= format_date($m['expiry_date']) ?> <?= $isExpired ? '(EXPIRED)' : '' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $isLow ? 'cancelled' : 'active' ?>" style="font-size:0.9rem;">
                                            <?= $m['quantity'] ?> Units
                                        </span>
                                    </td>
                                    <td><strong><?= format_currency($m['unit_price']) ?></strong></td>
                                    <td><?= $m['reorder_level'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="openStockModal(<?= $m['id'] ?>, '<?= escape($m['name']) ?>')">Adjust Stock</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Add Medicine Modal -->
<div id="add-med-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Add New Medicine to Stock</div>
            <button class="modal-close" onclick="closeModal('add-med-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_medicine">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Brand Medicine Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Amoxicillin 500mg" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Generic Name</label>
                        <input type="text" name="generic_name" class="form-control" placeholder="e.g. Amoxicillin" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Antibiotic" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control" placeholder="e.g. PharmaCare Ltd">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Batch Number</label>
                        <input type="text" name="batch_number" class="form-control" placeholder="e.g. BCH-9901" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Initial Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="100" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit Price ($)</label>
                        <input type="number" step="0.01" name="unit_price" class="form-control" value="10.00" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level Alert</label>
                        <input type="number" name="reorder_level" class="form-control" value="15" min="1" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-med-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Medicine</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stock-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Adjust Stock: <span id="stock-med-title"></span></div>
            <button class="modal-close" onclick="closeModal('stock-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" id="stock-med-id" name="medicine_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Transaction Type</label>
                    <select name="transaction_type" class="form-select" required>
                        <option value="STOCK_IN">STOCK IN (Add Inventory Batch)</option>
                        <option value="STOCK_OUT">STOCK OUT (Damage / Return / Adjustment)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="adjust_quantity" class="form-control" value="10" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Adjustment Notes / Reference</label>
                    <input type="text" name="notes" class="form-control" placeholder="Reason for inventory adjustment...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('stock-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Stock Level</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStockModal(id, name) {
    document.getElementById('stock-med-id').value = id;
    document.getElementById('stock-med-title').textContent = name;
    openModal('stock-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
