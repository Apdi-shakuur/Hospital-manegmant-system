<?php
/**
 * Billing & Invoice Generation Overview
 * Hospital Management System (HMS)
 */

$pageTitle = 'Billing & Invoices';
$activeNav = 'billing';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Receptionist', 'Patient']);

$db = Database::getInstance();
$user = current_user();
$message = '';
$messageType = 'success';

// Handle Create Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create_invoice') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $itemType  = $_POST['item_type'] ?? 'Consultation';
        $desc      = sanitize($_POST['description'] ?? '');
        $subtotal  = (float)($_POST['subtotal'] ?? 0.00);
        $discount  = (float)($_POST['discount'] ?? 0.00);
        $tax       = (float)($_POST['tax'] ?? 0.00);

        if ($patientId <= 0 || empty($desc) || $subtotal <= 0) {
            $message = 'Patient, Item Description, and Subtotal are required.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                $invNum = generate_code('INV');
                $dueDate = date('Y-m-d', strtotime('+14 days'));
                $total = max(0.00, ($subtotal - $discount) + $tax);

                $stmt = $db->prepare("
                    INSERT INTO invoices (invoice_number, patient_id, invoice_date, due_date, subtotal, discount, tax, total_amount, amount_paid, balance, status, created_by) 
                    VALUES (:num, :pid, CURRENT_DATE(), :due, :sub, :disc, :tax, :total, 0.00, :total, 'Unpaid', :uid)
                ");
                $stmt->execute([
                    ':num'   => $invNum,
                    ':pid'   => $patientId,
                    ':due'   => $dueDate,
                    ':sub'   => $subtotal,
                    ':disc'  => $discount,
                    ':tax'   => $tax,
                    ':total' => $total,
                    ':uid'   => $user['id']
                ]);
                $invId = (int)$db->lastInsertId();

                // Itemize line item
                $itemStmt = $db->prepare("
                    INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price) 
                    VALUES (:iid, :itype, :desc, 1, :price, :price)
                ");
                $itemStmt->execute([
                    ':iid'   => $invId,
                    ':itype' => $itemType,
                    ':desc'  => $desc,
                    ':price' => $subtotal
                ]);

                $db->commit();
                log_activity($db, $user['id'], 'Invoice Created', "Created invoice {$invNum} for Patient ID {$patientId} total {$total}.");
                $message = "Invoice {$invNum} generated successfully.";
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Inv Create Error: " . $e->getMessage());
                $message = 'Error generating invoice.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch Invoices List
$sql = "
    SELECT i.*, p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number, p.phone AS pat_phone 
    FROM invoices i 
    JOIN patients p ON i.patient_id = p.id 
    WHERE 1=1
";
$params = [];

if ($user['role_name'] === 'Patient') {
    $sql .= " AND p.user_id = :uid";
    $params[':uid'] = $user['id'];
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$patients = $db->query("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Billing & Patient Invoices</h1>
                <div class="page-subtitle">Generate patient statements, track outstanding balances, and print receipts</div>
            </div>
            <?php if (has_role(['Admin', 'Receptionist'])): ?>
                <div style="display:flex; gap:0.5rem;">
                    <a href="payments.php" class="btn btn-secondary">Payments History</a>
                    <button class="btn btn-primary" onclick="openModal('create-inv-modal')">+ Create Invoice</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem;">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Patient Name</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Total Amount</th>
                            <th>Amount Paid</th>
                            <th>Balance Due</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="9" style="text-align:center; color:var(--slate-400); padding:2rem;">No billing invoices found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($inv['invoice_number']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($inv['pat_fn'] . ' ' . $inv['pat_ln']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($inv['patient_number']) ?></div>
                                    </td>
                                    <td><?= format_date($inv['invoice_date']) ?></td>
                                    <td><?= format_date($inv['due_date']) ?></td>
                                    <td><strong><?= format_currency($inv['total_amount']) ?></strong></td>
                                    <td><?= format_currency($inv['amount_paid']) ?></td>
                                    <td><strong style="color:<?= $inv['balance'] > 0 ? 'var(--rose-600)' : 'var(--emerald-600)' ?>;"><?= format_currency($inv['balance']) ?></strong></td>
                                    <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $inv['status'])) ?>"><?= escape($inv['status']) ?></span></td>
                                    <td>
                                        <a href="view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-secondary">View / Print</a>
                                        <?php if (has_role(['Admin', 'Receptionist']) && $inv['status'] !== 'Paid'): ?>
                                            <a href="payments.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success">Record Payment</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Create Invoice Modal -->
<div id="create-inv-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Generate Patient Invoice</div>
            <button class="modal-close" onclick="closeModal('create-inv-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_invoice">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Patient</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- Choose Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= escape($p['patient_number']) ?> - <?= escape($p['first_name'] . ' ' . $p['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Service / Billable Category</label>
                    <select name="item_type" class="form-select" required>
                        <option value="Consultation">Doctor Consultation</option>
                        <option value="Lab Test">Laboratory Tests</option>
                        <option value="Medicine">Pharmacy Medications</option>
                        <option value="Procedure">Surgical Procedure</option>
                        <option value="Other">Other Hospital Services</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Item Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Cardiology Specialist Consultation Fee" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Subtotal ($)</label>
                        <input type="number" step="0.01" name="subtotal" class="form-control" value="100.00" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Discount ($)</label>
                        <input type="number" step="0.01" name="discount" class="form-control" value="0.00" min="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tax ($)</label>
                        <input type="number" step="0.01" name="tax" class="form-control" value="0.00" min="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-inv-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Invoice</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
