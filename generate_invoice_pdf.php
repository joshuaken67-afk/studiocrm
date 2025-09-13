<?php
/**
 * Generate Invoice PDF
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$invoiceId = $_GET['id'] ?? '';
if (!$invoiceId) {
    die('Invoice ID required');
}

$db = Database::getInstance();

// Get invoice with client details
$invoice = $db->fetchOne("
    SELECT i.*, c.name as client_name, c.email as client_email, 
           c.phone_number, c.service_interest,
           u.name as created_by_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
", [$invoiceId]);

if (!$invoice) {
    die('Invoice not found');
}

// Log the PDF generation
$auth->logAction($_SESSION['user_id'], 'generate_pdf', 'invoices', $invoiceId);

// Set headers for PDF display
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .company-info h1 {
            color: #007bff;
            margin: 0 0 10px 0;
        }
        .company-info p {
            margin: 5px 0;
            color: #666;
        }
        .invoice-details {
            text-align: right;
        }
        .invoice-details h2 {
            color: #007bff;
            margin: 0 0 10px 0;
        }
        .invoice-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .client-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .client-info h3 {
            margin: 0 0 15px 0;
            color: #007bff;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th,
        .invoice-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .invoice-table th {
            background: #007bff;
            color: white;
        }
        .total-section {
            text-align: right;
            margin-bottom: 40px;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            border-top: 2px solid #007bff;
            padding-top: 10px;
            display: inline-block;
            min-width: 200px;
        }
        .payment-terms {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .signature-section {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 200px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            Print Invoice
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="invoice-header">
        <div class="company-info">
            <h1><?= COMPANY_NAME ?></h1>
            <p><?= COMPANY_ADDRESS ?></p>
            <p>Phone: <?= COMPANY_PHONE ?></p>
            <p>Email: <?= COMPANY_EMAIL ?></p>
        </div>
        <div class="invoice-details">
            <h2>INVOICE</h2>
            <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
            <p><strong>Date:</strong> <?= date('F j, Y', strtotime($invoice['created_at'])) ?></p>
            <p><strong>Due Date:</strong> <?= date('F j, Y', strtotime($invoice['due_date'])) ?></p>
            <p><strong>Status:</strong> 
                <span style="color: <?= $invoice['status'] == 'paid' ? '#28a745' : ($invoice['status'] == 'overdue' ? '#dc3545' : '#007bff') ?>">
                    <?= ucfirst($invoice['status']) ?>
                </span>
            </p>
        </div>
    </div>

    <div class="client-info">
        <h3>Bill To:</h3>
        <p><strong><?= htmlspecialchars($invoice['client_name']) ?></strong></p>
        <p><?= htmlspecialchars($invoice['client_email']) ?></p>
        <?php if ($invoice['phone_number']): ?>
        <p><?= htmlspecialchars($invoice['phone_number']) ?></p>
        <?php endif; ?>
        <?php if ($invoice['service_interest']): ?>
        <p><em>Service: <?= htmlspecialchars($invoice['service_interest']) ?></em></p>
        <?php endif; ?>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Rate</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($invoice['service_interest'] ?: 'Studio Services') ?></td>
                <td>1</td>
                <td>$<?= number_format($invoice['amount'], 2) ?></td>
                <td>$<?= number_format($invoice['amount'], 2) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-amount">
            Total: $<?= number_format($invoice['amount'], 2) ?>
        </div>
    </div>

    <div class="payment-terms">
        <h4>Payment Terms & Instructions:</h4>
        <ul>
            <li>Payment is due within 30 days of invoice date</li>
            <li>Late payments may incur additional fees</li>
            <li>Please reference invoice number <?= htmlspecialchars($invoice['invoice_number']) ?> with your payment</li>
            <li>For questions about this invoice, please contact us at <?= COMPANY_EMAIL ?></li>
        </ul>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Client Signature
            </div>
            <p>Date: _______________</p>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <?= COMPANY_NAME ?> Representative
            </div>
            <p>Date: _______________</p>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p><?= COMPANY_NAME ?> | <?= COMPANY_PHONE ?> | <?= COMPANY_EMAIL ?></p>
        <p>Invoice generated on <?= date('F j, Y g:i A') ?> by <?= htmlspecialchars($invoice['created_by_name']) ?></p>
    </div>
</body>
</html>
