<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/ledger.php';
require_once 'includes/pdf-generator.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$current_user = getCurrentUser();
$ledger = new LedgerManager($pdo);
$pdf_generator = new DocumentGenerator($pdo, $ledger);

// Handle PDF generation requests
if (isset($_GET['type']) && isset($_GET['action']) && $_GET['action'] === 'generate') {
    try {
        switch ($_GET['type']) {
            case 'weekly_ledger':
                $week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
                $week_end = $_GET['week_end'] ?? date('Y-m-d', strtotime('sunday this week'));
                
                $pdf_generator->generateWeeklyLedgerReport($week_start, $week_end);
                exit;
                
            case 'project_financial':
                if (!isset($_GET['project_id'])) {
                    throw new Exception("Project ID required");
                }
                
                $pdf_generator->generateProjectFinancialSheet($_GET['project_id']);
                exit;
                
            case 'invoice':
                if (!isset($_GET['invoice_id'])) {
                    throw new Exception("Invoice ID required");
                }
                
                $pdf_generator->generateInvoice($_GET['invoice_id']);
                exit;
                
            default:
                throw new Exception("Invalid PDF type");
        }
    } catch (Exception $e) {
        $error_message = "Error generating PDF: " . $e->getMessage();
    }
}

// Get data for form options
$projects_sql = "SELECT p.id, p.service, c.name as client_name FROM projects p 
                 LEFT JOIN clients c ON p.client_id = c.id 
                 ORDER BY p.created_at DESC";
$projects_stmt = $pdo->query($projects_sql);
$projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

$invoices_sql = "SELECT i.id, i.invoice_number, c.name as client_name, i.total_amount 
                 FROM invoices i 
                 LEFT JOIN clients c ON i.client_id = c.id 
                 ORDER BY i.created_at DESC LIMIT 50";
$invoices_stmt = $pdo->query($invoices_sql);
$invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">PDF Document Generator</h1>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Weekly Ledger Report -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Weekly Ledger Report</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">Generate comprehensive weekly financial reports with signature blocks for printing and archiving.</p>
                            
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="weekly_ledger">
                                <input type="hidden" name="action" value="generate">
                                
                                <div class="mb-3">
                                    <label for="week_start" class="form-label">Week Start</label>
                                    <input type="date" class="form-control" id="week_start" name="week_start" 
                                           value="<?php echo date('Y-m-d', strtotime('monday this week')); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="week_end" class="form-label">Week End</label>
                                    <input type="date" class="form-control" id="week_end" name="week_end" 
                                           value="<?php echo date('Y-m-d', strtotime('sunday this week')); ?>" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-file-earmark-pdf"></i> Generate Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Project Financial Sheet -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Project Financial Sheet</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">Generate detailed financial breakdown for specific projects with client acknowledgment sections.</p>
                            
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="project_financial">
                                <input type="hidden" name="action" value="generate">
                                
                                <div class="mb-3">
                                    <label for="project_id" class="form-label">Select Project</label>
                                    <select class="form-select" id="project_id" name="project_id" required>
                                        <option value="">Choose Project</option>
                                        <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>">
                                            <?php echo htmlspecialchars($project['service'] . ' - ' . $project['client_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-file-earmark-pdf"></i> Generate Sheet
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Invoice Generator -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">Professional Invoice</h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text">Generate professional invoices with signature blocks and company branding.</p>
                            
                            <form method="GET" class="mb-3">
                                <input type="hidden" name="type" value="invoice">
                                <input type="hidden" name="action" value="generate">
                                
                                <div class="mb-3">
                                    <label for="invoice_id" class="form-label">Select Invoice</label>
                                    <select class="form-select" id="invoice_id" name="invoice_id" required>
                                        <option value="">Choose Invoice</option>
                                        <?php foreach ($invoices as $invoice): ?>
                                        <option value="<?php echo $invoice['id']; ?>">
                                            #<?php echo htmlspecialchars($invoice['invoice_number']); ?> - 
                                            <?php echo htmlspecialchars($invoice['client_name']); ?> 
                                            (₦<?php echo number_format($invoice['total_amount'], 2); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="bi bi-file-earmark-pdf"></i> Generate Invoice
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <a href="?type=weekly_ledger&action=generate" class="btn btn-outline-primary w-100 mb-2">
                                        Current Week Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="?type=weekly_ledger&action=generate&week_start=<?php echo date('Y-m-d', strtotime('monday last week')); ?>&week_end=<?php echo date('Y-m-d', strtotime('sunday last week')); ?>" 
                                       class="btn btn-outline-secondary w-100 mb-2">
                                        Last Week Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="?type=weekly_ledger&action=generate&week_start=<?php echo date('Y-m-01'); ?>&week_end=<?php echo date('Y-m-t'); ?>" 
                                       class="btn btn-outline-info w-100 mb-2">
                                        Monthly Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-outline-success w-100 mb-2" onclick="window.print()">
                                        Print This Page
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Templates Info -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Document Features</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>All Documents Include:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check-circle text-success"></i> Professional 148 Studios branding</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Signature blocks for authorization</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Date and page numbering</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Naira (₦) currency formatting</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Print-ready layout</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Security Features:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-shield-check text-primary"></i> Generated timestamp</li>
                                        <li><i class="bi bi-shield-check text-primary"></i> User tracking</li>
                                        <li><i class="bi bi-shield-check text-primary"></i> Audit trail integration</li>
                                        <li><i class="bi bi-shield-check text-primary"></i> Role-based access</li>
                                        <li><i class="bi bi-shield-check text-primary"></i> Secure file storage</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Auto-set week end date when week start changes
document.getElementById('week_start').addEventListener('change', function() {
    const startDate = new Date(this.value);
    const endDate = new Date(startDate);
    endDate.setDate(startDate.getDate() + 6); // Add 6 days for a full week
    
    const weekEndInput = document.getElementById('week_end');
    weekEndInput.value = endDate.toISOString().split('T')[0];
});
</script>

<?php include 'includes/footer.php'; ?>
