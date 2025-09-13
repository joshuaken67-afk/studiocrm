<?php
/**
 * Payments Management Page
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (!$auth->validateCSRFToken($_POST['_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'add_payment') {
            $data = [
                'client_id' => $_POST['client_id'],
                'amount' => $_POST['amount'],
                'payment_method' => $_POST['payment_method'],
                'payment_date' => $_POST['payment_date'],
                'description' => $_POST['description'],
                'recorded_by' => $_SESSION['user_id']
            ];
            
            try {
                $paymentId = $db->insert('payments', $data);
                $auth->logAction($_SESSION['user_id'], 'create', 'payments', $paymentId, null, $data);
                $message = 'Payment recorded successfully';
            } catch (Exception $e) {
                $error = 'Error recording payment: ' . $e->getMessage();
            }
        } elseif ($action == 'create_invoice') {
            // Generate invoice number
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $data = [
                'client_id' => $_POST['client_id'],
                'invoice_number' => $invoiceNumber,
                'amount' => $_POST['amount'],
                'due_date' => $_POST['due_date'],
                'status' => 'draft',
                'created_by' => $_SESSION['user_id']
            ];
            
            try {
                $invoiceId = $db->insert('invoices', $data);
                $auth->logAction($_SESSION['user_id'], 'create', 'invoices', $invoiceId, null, $data);
                $message = 'Invoice created successfully with number: ' . $invoiceNumber;
            } catch (Exception $e) {
                $error = 'Error creating invoice: ' . $e->getMessage();
            }
        } elseif ($action == 'update_invoice_status') {
            $invoiceId = $_POST['invoice_id'];
            $status = $_POST['status'];
            
            $oldInvoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
            $db->update('invoices', ['status' => $status], 'id = ?', [$invoiceId]);
            $auth->logAction($_SESSION['user_id'], 'update', 'invoices', $invoiceId, $oldInvoice, ['status' => $status]);
            $message = 'Invoice status updated successfully';
        }
    }
}

// Get payments and invoices with filters
$clientId = $_GET['client_id'] ?? '';
$type = $_GET['type'] ?? 'payments'; // payments or invoices
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * RECORDS_PER_PAGE;

$whereClause = '';
$params = [];
if ($clientId) {
    $whereClause = "WHERE client_id = ?";
    $params = [$clientId];
}

if ($type == 'payments') {
    $records = $db->fetchAll("
        SELECT p.*, c.name as client_name, c.email as client_email,
               u.name as recorded_by_name
        FROM payments p
        JOIN clients c ON p.client_id = c.id
        LEFT JOIN users u ON p.recorded_by = u.id
        $whereClause
        ORDER BY p.payment_date DESC, p.created_at DESC
        LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset
    ", $params);
    
    $totalRecords = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM payments p 
        JOIN clients c ON p.client_id = c.id 
        $whereClause
    ", $params)['count'];
} else {
    $records = $db->fetchAll("
        SELECT i.*, c.name as client_name, c.email as client_email,
               u.name as created_by_name
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        LEFT JOIN users u ON i.created_by = u.id
        $whereClause
        ORDER BY i.created_at DESC
        LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset
    ", $params);
    
    $totalRecords = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        $whereClause
    ", $params)['count'];
}

$totalPages = ceil($totalRecords / RECORDS_PER_PAGE);

// Get clients for dropdown
$clients = $db->fetchAll("SELECT id, name, email FROM clients WHERE status = 'active' ORDER BY name");

// Get financial summary
$summary = [
    'total_payments' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM payments" . ($clientId ? " WHERE client_id = $clientId" : ""))['total'],
    'total_invoiced' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE status != 'draft'" . ($clientId ? " AND client_id = $clientId" : ""))['total'],
    'pending_invoices' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE status IN ('sent', 'overdue')" . ($clientId ? " AND client_id = $clientId" : ""))['total'],
    'overdue_invoices' => $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE status = 'overdue' AND due_date < CURDATE()" . ($clientId ? " AND client_id = $clientId" : ""))['count']
];

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Payments & Invoices</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="bi bi-plus"></i> Record Payment
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                        <i class="bi bi-file-earmark-plus"></i> Create Invoice
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Financial Summary -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Payments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($summary['total_payments'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-cash-coin fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Invoiced</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($summary['total_invoiced'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-file-earmark-text fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Invoices</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($summary['pending_invoices'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue Invoices</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $summary['overdue_invoices'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and View Toggle -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select name="client_id" class="form-select" onchange="updateFilter('client_id', this.value)">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="type" id="paymentsView" value="payments" <?= $type == 'payments' ? 'checked' : '' ?> onchange="updateFilter('type', 'payments')">
                                <label class="btn btn-outline-primary" for="paymentsView">
                                    <i class="bi bi-cash"></i> Payments
                                </label>
                                
                                <input type="radio" class="btn-check" name="type" id="invoicesView" value="invoices" <?= $type == 'invoices' ? 'checked' : '' ?> onchange="updateFilter('type', 'invoices')">
                                <label class="btn btn-outline-primary" for="invoicesView">
                                    <i class="bi bi-file-earmark"></i> Invoices
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <a href="payments.php" class="btn btn-outline-secondary">Clear Filters</a>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="export.php?type=<?= $type ?><?= $clientId ? '&client_id=' . $clientId : '' ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Display -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($records)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-<?= $type == 'payments' ? 'cash-stack' : 'file-earmark-x' ?> display-1 text-muted"></i>
                            <h4 class="mt-3">No <?= ucfirst($type) ?> Found</h4>
                            <p class="text-muted">Start by <?= $type == 'payments' ? 'recording a payment' : 'creating an invoice' ?>.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <?php if ($type == 'payments'): ?>
                                            <th>Client</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Recorded By</th>
                                        <?php else: ?>
                                            <th>Invoice #</th>
                                            <th>Client</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                    <tr>
                                        <?php if ($type == 'payments'): ?>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($record['client_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($record['client_email']) ?></small>
                                            </td>
                                            <td class="fw-bold text-success">$<?= number_format($record['amount'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= ucfirst(str_replace('_', ' ', $record['payment_method'])) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($record['payment_date'])) ?></td>
                                            <td><?= htmlspecialchars($record['description']) ?></td>
                                            <td><?= htmlspecialchars($record['recorded_by_name']) ?></td>
                                        <?php else: ?>
                                            <td class="fw-bold"><?= htmlspecialchars($record['invoice_number']) ?></td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($record['client_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($record['client_email']) ?></small>
                                            </td>
                                            <td class="fw-bold">$<?= number_format($record['amount'], 2) ?></td>
                                            <td>
                                                <?= date('M j, Y', strtotime($record['due_date'])) ?>
                                                <?php if ($record['status'] != 'paid' && strtotime($record['due_date']) < time()): ?>
                                                    <br><small class="text-danger">Overdue</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $record['status'] == 'paid' ? 'success' : 
                                                    ($record['status'] == 'sent' ? 'primary' : 
                                                    ($record['status'] == 'overdue' ? 'danger' : 'secondary')) 
                                                ?>">
                                                    <?= ucfirst($record['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="generate_invoice_pdf.php?id=<?= $record['id'] ?>" class="btn btn-outline-primary" target="_blank">
                                                        <i class="bi bi-file-earmark-pdf"></i>
                                                    </a>
                                                    <div class="dropdown">
                                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            Status
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item" href="#" onclick="updateInvoiceStatus(<?= $record['id'] ?>, 'draft')">Draft</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="updateInvoiceStatus(<?= $record['id'] ?>, 'sent')">Sent</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="updateInvoiceStatus(<?= $record['id'] ?>, 'paid')">Paid</a></li>
                                                            <li><a class="dropdown-item" href="#" onclick="updateInvoiceStatus(<?= $record['id'] ?>, 'overdue')">Overdue</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&type=<?= $type ?><?= $clientId ? '&client_id=' . $clientId : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add_payment">
                    
                    <div class="mb-3">
                        <label class="form-label">Client *</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Select a client...</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="check">Check</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Payment for services, project milestone, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create_invoice">
                    
                    <div class="mb-3">
                        <label class="form-label">Client *</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Select a client...</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Due Date *</label>
                                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Invoice number will be automatically generated. You can generate a PDF after creation.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateFilter(param, value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(param, value);
    } else {
        url.searchParams.delete(param);
    }
    window.location = url;
}

function updateInvoiceStatus(invoiceId, status) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
        <input type="hidden" name="action" value="update_invoice_status">
        <input type="hidden" name="invoice_id" value="${invoiceId}">
        <input type="hidden" name="status" value="${status}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include 'includes/footer.php'; ?>
