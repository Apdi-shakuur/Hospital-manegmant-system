<?php
/**
 * Payment Transaction Logger & History
 * Hospital Management System (HMS)
 */

$pageTitle = 'Record Payments';
$activeNav = 'billing';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Receptionist']);

$db = Database::getInstance();
$user = current_user();
$message = '';
$messageType = 'success';

// Handle Record Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'record_payment') {
        $invId   = (int)($_POST['invoice_id'] ?? 0);
        $amount  = (float)($_POST['amount'] ?? 0.00);
        $method  = $_POST['payment_method'] ?? 'Cash';
        $refNum  = sanitize($_POST['reference_number'] ?? '');

        if ($invId <= 0 || $amount <= 0.00) {
            $message = 'Valid invoice selection and positive payment amount required.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                // Fetch invoice balance and patient_id
                $iStmt = $db->prepare("SELECT patient_id, total_amount, amount_paid, balance FROM invoices WHERE id = :id FOR UPDATE");
                $iStmt->execute([':id' => $invId]);
                $inv = $iStmt->fetch();

                if (!$inv) {
                    $db->rollBack();
                    $message = 'Invoice not found.';
                    $messageType = 'danger';
                } else {
                    $newPaid = $inv['amount_paid'] + $amount;
                    $newBalance = max(0.00, $inv['total_amount'] - $newPaid);
                    $newStatus = ($newBalance <= 0.00) ? 'Paid' : 'Partially Paid';

                    // Save Payment Record
                    $payNum = generate_code('PAY');
                    $pStmt = $db->prepare("
                        INSERT INTO payments (payment_number, invoice_id, patient_id, amount, payment_method, reference_number, received_by) 
                        VALUES (:num, :iid, :pid, :amt, :pm, :ref, :uid)
                    ");
                    $pStmt->execute([
                        ':num' => $payNum,
                        ':iid' => $invId,
                        ':pid' => $inv['patient_id'],
                        ':amt' => $amount,
                        ':pm'  => $method,
                        ':ref' => $refNum,
                        ':uid' => $user['id']
                    ]);

                    // Update Invoice Status and Balance
                    $uInv = $db->prepare("UPDATE invoices SET amount_paid = :paid, balance = :bal, status = :st WHERE id = :id");
                    $uInv->execute([
                        ':paid' => $newPaid,
                        ':bal'  => $newBalance,
                        ':st'   => $newStatus,
                        ':id'   => $invId
                    ]);

                    $db->commit();
                    log_activity($db, $user['id'], 'Payment Recorded', "Recorded {$method} payment {$payNum} of \$" . number_format($amount, 2) . " for Invoice ID {$invId}.");
                    $message = "Payment {$payNum} recorded successfully! Invoice status is now '{$newStatus}'.";
                }
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Payment Error: " . $e->getMessage());
                $message = 'Error recording payment.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch Payments History
$payments = $db->query("
    SELECT pay.*, i.invoice_number, p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number 
    FROM payments pay 
    JOIN invoices i ON pay.invoice_id = i.id 
    JOIN patients p ON pay.patient_id = p.id 
    ORDER BY pay.payment_date DESC
")->fetchAll();

$unpaidInvoices = $db->query("
    SELECT i.id, i.invoice_number, i.balance, p.first_name, p.last_name 
    FROM invoices i 
    JOIN patients p ON i.patient_id = p.id 
    WHERE i.status IN ('Unpaid', 'Partially Paid') 
    ORDER BY i.invoice_date ASC
")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Payment Collections Log</h1>
                <div class="page-subtitle">Receive patient payments via Cash, Card, Mobile Money, or Bank Transfer</div>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <a href="index.php" class="btn btn-secondary">&larr; Invoices Overview</a>
                <button class="btn btn-primary" onclick="openModal('add-pay-modal')">+ Record Payment</button>
            </div>
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
                            <th>Receipt #</th>
                            <th>Invoice #</th>
                            <th>Patient Name</th>
                            <th>Amount Paid</th>
                            <th>Method</th>
                            <th>Reference #</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--slate-400); padding:2rem;">No payment transactions recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($pay['payment_number']) ?></strong></td>
                                    <td><a href="view.php?id=<?= $pay['invoice_id'] ?>" style="color:var(--slate-800); font-weight:700; text-decoration:none;"><?= escape($pay['invoice_number']) ?></a></td>
                                    <td>
                                        <strong><?= escape($pay['pat_fn'] . ' ' . $pay['pat_ln']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($pay['patient_number']) ?></div>
                                    </td>
                                    <td><strong style="color:var(--emerald-600); font-size:1rem;"><?= format_currency($pay['amount']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= escape($pay['payment_method']) ?></span></td>
                                    <td><code><?= escape($pay['reference_number'] ?: 'N/A') ?></code></td>
                                    <td><?= format_datetime($pay['payment_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Record Payment Modal -->
<div id="add-pay-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Record Invoice Payment</div>
            <button class="modal-close" onclick="closeModal('add-pay-modal')">&times;</button>
        </div>
        <form method="POST" action="payments.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Unpaid / Partial Invoice</label>
                    <select name="invoice_id" class="form-select" required>
                        <option value="">-- Choose Outstanding Invoice --</option>
                        <?php foreach ($unpaidInvoices as $inv): ?>
                            <option value="<?= $inv['id'] ?>" <?= (($_GET['invoice_id'] ?? 0) == $inv['id']) ? 'selected' : '' ?>>
                                <?= escape($inv['invoice_number']) ?> - <?= escape($inv['first_name'] . ' ' . $inv['last_name']) ?> (Balance: $<?= number_format($inv['balance'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Amount ($)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" value="50.00" min="0.01" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="Cash">Cash</option>
                        <option value="Card">Credit / Debit Card</option>
                        <option value="Mobile Money">Mobile Money (M-Pesa / Mobile Wallet)</option>
                        <option value="Bank Transfer">Bank Wire Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Transaction Reference # (Optional)</label>
                    <input type="text" name="reference_number" class="form-control" placeholder="e.g. TXN-VISA-99821 or Cash receipt #">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-pay-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Issue Receipt</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
