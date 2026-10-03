<?php
/**
 * Prescription Dispensing & Stock Reduction Engine
 * Hospital Management System (HMS)
 */

$pageTitle = 'Dispense Prescriptions';
$activeNav = 'pharmacy';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Pharmacist']);

$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Handle Dispense Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    $rxId = (int)($_POST['prescription_id'] ?? 0);

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'dispense') {
        if ($rxId <= 0) {
            $message = 'Invalid prescription ID.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                // Fetch prescription items
                $itemsStmt = $db->prepare("
                    SELECT pi.*, m.name AS med_name, m.quantity AS avail_qty 
                    FROM prescription_items pi 
                    JOIN medicines m ON pi.medicine_id = m.id 
                    WHERE pi.prescription_id = :rxid
                ");
                $itemsStmt->execute([':rxid' => $rxId]);
                $items = $itemsStmt->fetchAll();

                // Validate sufficient stock for ALL items
                $insufficient = [];
                foreach ($items as $item) {
                    if ($item['avail_qty'] < $item['quantity']) {
                        $insufficient[] = "'{$item['med_name']}' (Requested: {$item['quantity']}, Available: {$item['avail_qty']})";
                    }
                }

                if (!empty($insufficient)) {
                    $db->rollBack();
                    $message = 'Dispensing Blocked! Insufficient stock for: ' . implode(', ', $insufficient);
                    $messageType = 'danger';
                } else {
                    // Deduct stock for each item & add stock log
                    foreach ($items as $item) {
                        $deductStmt = $db->prepare("UPDATE medicines SET quantity = quantity - :qty WHERE id = :mid");
                        $deductStmt->execute([':qty' => $item['quantity'], ':mid' => $item['medicine_id']]);

                        $logStmt = $db->prepare("
                            INSERT INTO medicine_stock_log (medicine_id, transaction_type, quantity, reference_id, notes, created_by) 
                            VALUES (:mid, 'DISPENSED', :qty, :ref, 'Dispensed for Prescription', :uid)
                        ");
                        $logStmt->execute([
                            ':mid' => $item['medicine_id'],
                            ':qty' => $item['quantity'],
                            ':ref' => 'RX-' . $rxId,
                            ':uid' => current_user()['id']
                        ]);
                    }

                    // Update prescription status to Dispensed
                    $upRx = $db->prepare("UPDATE prescriptions SET status = 'Dispensed' WHERE id = :rxid");
                    $upRx->execute([':rxid' => $rxId]);

                    $db->commit();
                    log_activity($db, current_user()['id'], 'Prescription Dispensed', "Dispensed prescription ID {$rxId} and auto-deducted inventory stock.");
                    $message = "Prescription successfully dispensed! Stock quantities automatically updated.";
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Dispense Error: " . $e->getMessage());
                $message = 'Error processing prescription dispensing.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch Pending Prescriptions
$pendingRx = $db->query("
    SELECT pr.*, 
           p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number,
           d.first_name AS doc_fn, d.last_name AS doc_ln 
    FROM prescriptions pr 
    JOIN patients p ON pr.patient_id = p.id 
    JOIN doctors d ON pr.doctor_id = d.id 
    WHERE pr.status = 'Pending' 
    ORDER BY pr.prescription_date ASC
")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Prescription Dispensing Portal</h1>
                <div class="page-subtitle">Validate stock levels, dispense medications, and auto-update pharmacy inventory</div>
            </div>
            <a href="index.php" class="btn btn-secondary">&larr; Return to Stock Inventory</a>
        </div>

        <?php if ($message): ?>
            <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem;">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Pending Prescription Queue (<?= count($pendingRx) ?>)</div>
            </div>

            <?php if (empty($pendingRx)): ?>
                <div style="text-align:center; color:var(--slate-400); padding:3rem;">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-bottom:0.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>All pending prescriptions have been dispensed!</div>
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:1.5rem;">
                    <?php foreach ($pendingRx as $rx): ?>
                        <?php
                        $itemsStmt = $db->prepare("
                            SELECT pi.*, m.name AS med_name, m.quantity AS stock_qty, m.unit_price 
                            FROM prescription_items pi 
                            JOIN medicines m ON pi.medicine_id = m.id 
                            WHERE pi.prescription_id = :rxid
                        ");
                        $itemsStmt->execute([':rxid' => $rx['id']]);
                        $items = $itemsStmt->fetchAll();

                        $canDispense = true;
                        ?>
                        <div style="border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1.25rem; background:var(--slate-50);">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.75rem; margin-bottom:1rem;">
                                <div>
                                    <strong style="font-size:1.1rem; color:var(--primary-600);"><?= escape($rx['prescription_number']) ?></strong>
                                    <span style="margin-left:0.75rem; font-weight:700; font-size:0.95rem;">Patient: <?= escape($rx['pat_fn'] . ' ' . $rx['pat_ln']) ?> (<?= escape($rx['patient_number']) ?>)</span>
                                </div>
                                <div style="font-size:0.85rem; color:var(--slate-500);">
                                    Issued by: Dr. <?= escape($rx['doc_fn'] . ' ' . $rx['doc_ln']) ?> | <?= format_datetime($rx['prescription_date']) ?>
                                </div>
                            </div>

                            <!-- Prescription Items Stock Validation List -->
                            <div class="table-responsive" style="margin-bottom:1rem; background:white;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Prescribed Drug Item</th>
                                            <th>Dosage & Frequency</th>
                                            <th>Req. Qty</th>
                                            <th>In Stock Qty</th>
                                            <th>Stock Check</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $hasStock = $item['stock_qty'] >= $item['quantity'];
                                            if (!$hasStock) $canDispense = false;
                                            ?>
                                            <tr>
                                                <td><strong><?= escape($item['med_name']) ?></strong></td>
                                                <td><?= escape($item['dosage']) ?>, <?= escape($item['frequency']) ?> (<?= escape($item['duration']) ?>)</td>
                                                <td><strong><?= $item['quantity'] ?> Units</strong></td>
                                                <td><?= $item['stock_qty'] ?> Units</td>
                                                <td>
                                                    <?php if ($hasStock): ?>
                                                        <span class="badge badge-completed">✓ Sufficient Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-cancelled">❌ Low Stock Alert</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <form method="POST" action="dispensing.php" style="text-align:right;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="dispense">
                                <input type="hidden" name="prescription_id" value="<?= $rx['id'] ?>">
                                
                                <button type="submit" class="btn btn-success" <?= !$canDispense ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                                    <?= $canDispense ? '✓ Confirm & Dispense Medicines' : 'Cannot Dispense (Stock Insufficient)' ?>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
