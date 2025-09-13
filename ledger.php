<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/ledger.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$ledger = new LedgerManager($pdo);
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_entry':
                try {
                    $data = [
                        'entry_date' => $_POST['entry_date'],
                        'entry_type' => $_POST['entry_type'],
                        'amount' => floatval($_POST['amount']),
                        'payment_method' => $_POST['payment_method'],
                        'description' => $_POST['description'],
                        'linked_project_id' => !empty($_POST['linked_project_id']) ? $_POST['linked_project_id'] : null,
                        'created_by' => $current_user['id']
                    ];
                    
                    if ($ledger->addLedgerEntry($data)) {
                        $success_message = "Ledger entry added successfully!";
                    } else {
                        $error_message = "Failed to add ledger entry.";
                    }
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
                
            case 'add_expense':
                try {
                    $data = [
                        'category' => $_POST['category'],
                        'subcategory' => $_POST['subcategory'],
                        'amount' => floatval($_POST['amount']),
                        'expense_date' => $_POST['expense_date'],
                        'description' => $_POST['description'],
                        'vendor_name' => $_POST['vendor_name'],
                        'payment_method' => $_POST['payment_method'],
                        'linked_project_id' => !empty($_POST['linked_project_id']) ? $_POST['linked_project_id'] : null,
                        'created_by' => $current_user['id']
                    ];
                    
                    if ($ledger->addExpense($data)) {
                        $success_message = "Expense added successfully!";
                    } else {
                        $error_message = "Failed to add expense.";
                    }
                } catch (Exception $e) {
                    $error_message = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get financial summary
$financial_summary = $ledger->getFinancialSummary();

// Get recent entries
$recent_entries = $ledger->getLedgerEntries(null, null, 20);

// Get projects for dropdown
$projects_sql = "SELECT p.id, p.service, c.name as client_name FROM projects p 
                 LEFT JOIN clients c ON p.client_id = c.id 
                 WHERE p.status IN ('pending', 'in-progress') 
                 ORDER BY p.created_at DESC";
$projects_stmt = $pdo->query($projects_sql);
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Ledger Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                        Add Entry
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                        Add Expense
                    </button>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Financial Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Investments</h5>
                            <h3>₦<?php echo number_format($financial_summary['total_investments'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Credits</h5>
                            <h3>₦<?php echo number_format($financial_summary['total_credits'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Debits</h5>
                            <h3>₦<?php echo number_format($financial_summary['total_debits'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">ROI</h5>
                            <h3><?php echo number_format($financial_summary['roi_percentage'], 2); ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Entries Table -->
            <div class="card">
                <div class="card-header">
                    <h5>Recent Ledger Entries</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount (₦)</th>
                                    <th>Method</th>
                                    <th>Description</th>
                                    <th>Project</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_entries as $entry): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $entry['entry_type'] === 'credit' ? 'success' : 
                                                ($entry['entry_type'] === 'debit' ? 'danger' : 'primary'); 
                                        ?>">
                                            <?php echo ucfirst($entry['entry_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($entry['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $entry['payment_method'])); ?></td>
                                    <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                    <td><?php echo $entry['project_name'] ? htmlspecialchars($entry['project_name']) : '-'; ?></td>
                                    <td><small><?php echo htmlspecialchars($entry['reference_number']); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Entry Modal -->
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Ledger Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_entry">
                    
                    <div class="mb-3">
                        <label for="entry_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="entry_date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="entry_type" class="form-label">Entry Type</label>
                        <select class="form-select" id="entry_type" name="entry_type" required>
                            <option value="">Select Type</option>
                            <option value="investment">Investment</option>
                            <option value="credit">Credit (Revenue)</option>
                            <option value="debit">Debit (Expense)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount (₦)</label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="check">Check</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="linked_project_id" class="form-label">Linked Project (Optional)</label>
                        <select class="form-select" id="linked_project_id" name="linked_project_id">
                            <option value="">No Project</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['service'] . ' - ' . $project['client_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_expense">
                    
                    <div class="mb-3">
                        <label for="expense_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Select Category</option>
                            <option value="rent">Rent</option>
                            <option value="utilities">Utilities</option>
                            <option value="salaries">Salaries</option>
                            <option value="contractor">Contractor</option>
                            <option value="equipment">Equipment</option>
                            <option value="marketing">Marketing</option>
                            <option value="misc">Miscellaneous</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subcategory" class="form-label">Subcategory (Optional)</label>
                        <input type="text" class="form-control" id="subcategory" name="subcategory">
                    </div>
                    
                    <div class="mb-3">
                        <label for="expense_amount" class="form-label">Amount (₦)</label>
                        <input type="number" step="0.01" class="form-control" id="expense_amount" name="amount" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="vendor_name" class="form-label">Vendor/Supplier</label>
                        <input type="text" class="form-control" id="vendor_name" name="vendor_name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="expense_payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="expense_payment_method" name="payment_method" required>
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="check">Check</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expense_project_id" class="form-label">Linked Project (Optional)</label>
                        <select class="form-select" id="expense_project_id" name="linked_project_id">
                            <option value="">General Business Expense</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['service'] . ' - ' . $project['client_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expense_description" class="form-label">Description</label>
                        <textarea class="form-control" id="expense_description" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
