<?php
/**
 * Printable Invoice Statement & Receipt Template
 * Hospital Management System (HMS)
 */

$pageTitle = 'Printable Invoice';
$activeNav = 'billing';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$db = Database::getInstance();
$invId = (int)($_GET['id'] ?? 0);

if ($invId <= 0) {
    die("<div class='content-body'><div class='card'><h2>Invalid Invoice ID.</h2></div></div>");
}

$stmt = $db->prepare("
    SELECT i.*, 
           p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number, p.phone AS pat_phone, p.email AS pat_email, p.address AS pat_addr 
    FROM invoices i 
    JOIN patients p ON i.patient_id = p.id 
    WHERE i.id = :id
");
$stmt->execute([':id' => $invId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("<div class='content-body'><div class='card'><h2>Invoice Record Not Found.</h2></div></div>");
}

// Fetch line items
$itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = :id");
$itemsStmt->execute([':id' => $invId]);
$items = $itemsStmt->fetchAll();

// Fetch payment transactions
$payStmt = $db->prepare("SELECT * FROM payments WHERE invoice_id = :id ORDER BY payment_date ASC");
$payStmt->execute([':id' => $invId]);
$payments = $payStmt->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Invoice <?= escape($invoice['invoice_number']) ?></h1>
                <div class="page-subtitle">Printable Patient Statement & Official Hospital Receipt</div>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <a href="index.php" class="btn btn-secondary">&larr; Back to Invoices</a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print Invoice / Receipt</button>
            </div>
        </div>

        <!-- Printable Invoice Container -->
        <div class="card printable-area" style="padding: 2.5rem; max-width: 800px; margin: 0 auto; background: white;">
            <!-- Invoice Header & Hospital Branding -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid var(--slate-200); padding-bottom:1.5rem; margin-bottom:1.5rem;">
                <div>
                    <div style="font-size:1.6rem; font-weight:800; color:var(--primary-600); text-transform:uppercase; letter-spacing:-0.02em;">ModernCare Hospital</div>
                    <div style="font-size:0.85rem; color:var(--slate-500); margin-top:0.2rem;">100 Healthcare Boulevard, Suite 400</div>
                    <div style="font-size:0.85rem; color:var(--slate-500);">Phone: +1 (800) 555-CARE | Billing: billing@moderncare.org</div>
                </div>

                <div style="text-align:right;">
                    <h2 style="font-size:1.4rem; color:var(--slate-900); margin:0;">INVOICE</h2>
                    <div style="font-size:1rem; font-weight:700; color:var(--primary-600); margin-top:0.25rem;"><?= escape($invoice['invoice_number']) ?></div>
                    <div style="font-size:0.825rem; color:var(--slate-500); margin-top:0.25rem;">Date: <?= format_date($invoice['invoice_date']) ?></div>
                    <div style="font-size:0.825rem; color:var(--slate-500);">Due Date: <?= format_date($invoice['due_date']) ?></div>
                    <div style="margin-top:0.5rem;">
                        <span class="badge badge-<?= strtolower(str_replace(' ', '-', $invoice['status'])) ?>" style="font-size:0.85rem;"><?= escape($invoice['status']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Billed To Patient Details -->
            <div style="display:flex; justify-content:space-between; gap:2rem; margin-bottom:2rem; background:var(--slate-50); padding:1rem 1.25rem; border-radius:var(--radius-sm);">
                <div>
                    <div style="font-size:0.75rem; font-weight:700; color:var(--slate-400); text-transform:uppercase;">BILLED TO PATIENT</div>
                    <div style="font-size:1.05rem; font-weight:700; color:var(--slate-900); margin-top:0.2rem;"><?= escape($invoice['pat_fn'] . ' ' . $invoice['pat_ln']) ?></div>
                    <div style="font-size:0.85rem; color:var(--slate-600);">Patient ID: <?= escape($invoice['patient_number']) ?></div>
                    <div style="font-size:0.85rem; color:var(--slate-600);">Phone: <?= escape($invoice['pat_phone']) ?></div>
                    <div style="font-size:0.85rem; color:var(--slate-600);"><?= escape($invoice['pat_addr'] ?: 'City Center Address') ?></div>
                </div>
            </div>

            <!-- Line Items Table -->
            <table class="table" style="margin-bottom:1.5rem;">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th style="text-align:right;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><strong><?= escape($it['description']) ?></strong></td>
                            <td><span class="badge badge-info"><?= escape($it['item_type']) ?></span></td>
                            <td><?= $it['quantity'] ?></td>
                            <td><?= format_currency($it['unit_price']) ?></td>
                            <td style="text-align:right;"><strong><?= format_currency($it['total_price']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Summary Calculations -->
            <div style="display:flex; justify-content:flex-end; margin-bottom:2rem;">
                <div style="width:280px;">
                    <div style="display:flex; justify-content:space-between; padding:0.4rem 0; color:var(--slate-600);">
                        <span>Subtotal:</span>
                        <span><?= format_currency($invoice['subtotal']) ?></span>
                    </div>
                    <?php if ($invoice['discount'] > 0): ?>
                        <div style="display:flex; justify-content:space-between; padding:0.4rem 0; color:var(--emerald-600);">
                            <span>Discount:</span>
                            <span>- <?= format_currency($invoice['discount']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($invoice['tax'] > 0): ?>
                        <div style="display:flex; justify-content:space-between; padding:0.4rem 0; color:var(--slate-600);">
                            <span>Tax:</span>
                            <span>+ <?= format_currency($invoice['tax']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:space-between; padding:0.75rem 0; border-top:2px solid var(--slate-900); font-weight:800; font-size:1.15rem; color:var(--slate-900);">
                        <span>Total Billed:</span>
                        <span><?= format_currency($invoice['total_amount']) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:0.4rem 0; color:var(--emerald-600); font-weight:600;">
                        <span>Amount Paid:</span>
                        <span>- <?= format_currency($invoice['amount_paid']) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding:0.5rem 0; border-top:1px solid var(--slate-200); font-weight:800; font-size:1.1rem; color:<?= $invoice['balance'] > 0 ? 'var(--rose-600)' : 'var(--emerald-600)' ?>;">
                        <span>Balance Due:</span>
                        <span><?= format_currency($invoice['balance']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Recorded Payment Transactions -->
            <?php if (!empty($payments)): ?>
                <div style="margin-top:2rem; border-top:1px dashed var(--slate-300); padding-top:1.25rem;">
                    <h4 style="font-size:0.95rem; font-weight:700; color:var(--slate-800); margin-bottom:0.75rem;">Payment Transaction History</h4>
                    <table class="table" style="font-size:0.825rem;">
                        <thead>
                            <tr>
                                <th>Receipt #</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Date</th>
                                <th style="text-align:right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td><?= escape($p['payment_number']) ?></td>
                                    <td><?= escape($p['payment_method']) ?></td>
                                    <td><?= escape($p['reference_number'] ?: 'N/A') ?></td>
                                    <td><?= format_datetime($p['payment_date']) ?></td>
                                    <td style="text-align:right; color:var(--emerald-600); font-weight:700;"><?= format_currency($p['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="margin-top:3rem; text-align:center; font-size:0.8rem; color:var(--slate-400); border-top:1px solid var(--slate-200); padding-top:1rem;">
                Thank you for choosing ModernCare Hospital. For inquiries regarding this bill, contact billing support.
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
