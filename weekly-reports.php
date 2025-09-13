<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/ledger.php';
require_once 'includes/pdf-generator.php';
require_once 'includes/weekly-automation.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$current_user = getCurrentUser();

// Initialize classes
$ledger = new LedgerManager($pdo);
$pdf_generator = new DocumentGenerator($pdo, $ledger);
$weekly_automation = new WeeklyReportAutomation($pdo, $ledger, $pdf_generator);

// Handle manual report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_weekly') {
        try {
            $week_start = $_POST['week_start'];
            $week_end = $_POST['week_end'];
            
            $notification_data = $weekly_automation->generateWeeklyReports($week_start, $week_end);
            $success_message = "Weekly reports generated successfully for " . $notification_data['week_range'];
            
        } catch (Exception $e) {
            $error_message = "Error generating reports: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'mark_signed') {
        try {
            $report_id = $_POST['report_id'];
            $sql = "UPDATE weekly_reports SET report_status = 'signed', signed_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$report_id]);
            
            $success_message = "Report marked as signed successfully!";
        } catch (Exception $e) {
            $error_message = "Error updating report status: " . $e->getMessage();
        }
    }
}

// Get recent weekly reports
$reports_sql = "SELECT wr.*, 
                COUNT(CASE WHEN en.notification_type = 'weekly_reports' THEN 1 END) as notifications_sent
                FROM weekly_reports wr
                LEFT JOIN email_notifications en ON en.subject LIKE CONCAT('%Week ', wr.week_number, '%')
                GROUP BY wr.id
                ORDER BY wr.year DESC, wr.week_number DESC 
                LIMIT 10";
$reports_stmt = $pdo->query($reports_sql);
$recent_reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for pending signatures
$pending_report = $weekly_automation->checkPendingSignatures();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Weekly Report Automation</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                        Generate Reports
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

            <?php if ($pending_report): ?>
                <div class="alert alert-warning" role="alert">
                    <h5 class="alert-heading">Pending Signatures!</h5>
                    <p>Week <?php echo $pending_report['week_number']; ?> reports 
                       (<?php echo date('M j', strtotime($pending_report['week_start_date'])); ?> - 
                       <?php echo date('M j, Y', strtotime($pending_report['week_end_date'])); ?>) 
                       are still pending signature.</p>
                    <hr>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="mark_signed">
                        <input type="hidden" name="report_id" value="<?php echo $pending_report['id']; ?>">
                        <button type="submit" class="btn btn-warning btn-sm">Mark as Signed</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Automation Status -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">Automation Schedule</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Weekly Reports:</strong> Every Friday at 6:00 PM</p>
                            <p><strong>Reminder Checks:</strong> Every Monday at 9:00 AM</p>
                            <p><strong>Next Generation:</strong> <?php echo date('F j, Y \a\t 6:00 PM', strtotime('next friday')); ?></p>
                            
                            <div class="mt-3">
                                <h6>Cron Job Setup:</h6>
                                <small class="text-muted">
                                    <code>0 18 * * 5 php /path/to/cron/weekly-reports.php</code><br>
                                    <code>0 9 * * 1 php /path/to/cron/monday-reminders.php</code>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Generated Documents</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-file-earmark-pdf text-danger"></i> Weekly Ledger Report</li>
                                <li><i class="bi bi-file-earmark-pdf text-danger"></i> Master Weekly Binder</li>
                                <li><i class="bi bi-file-earmark-pdf text-danger"></i> Project Financial Sheets</li>
                                <li><i class="bi bi-file-earmark-pdf text-danger"></i> Staff Performance Reports</li>
                                <li><i class="bi bi-file-earmark-pdf text-danger"></i> Professional Invoices</li>
                            </ul>
                            <small class="text-muted">All documents include signature blocks for authorization</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Reports -->
            <div class="card">
                <div class="card-header">
                    <h5>Recent Weekly Reports</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Week</th>
                                    <th>Period</th>
                                    <th>Profit</th>
                                    <th>ROI</th>
                                    <th>Status</th>
                                    <th>Generated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_reports as $report): ?>
                                <tr>
                                    <td>Week <?php echo $report['week_number']; ?>, <?php echo $report['year']; ?></td>
                                    <td>
                                        <?php echo date('M j', strtotime($report['week_start_date'])); ?> - 
                                        <?php echo date('M j', strtotime($report['week_end_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo $report['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            ₦<?php echo number_format($report['profit'], 2); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($report['roi_percentage'], 2); ?>%</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $report['report_status'] === 'signed' ? 'success' : 
                                                ($report['report_status'] === 'printed' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($report['report_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($report['generated_at'])); ?></td>
                                    <td>
                                        <?php if ($report['pdf_file'] && file_exists($report['pdf_file'])): ?>
                                            <a href="<?php echo $report['pdf_file']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($report['report_status'] === 'generated'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="mark_signed">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-check"></i> Mark Signed
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
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

<!-- Generate Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Weekly Reports</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate_weekly">
                    
                    <div class="mb-3">
                        <label for="week_start" class="form-label">Week Start Date</label>
                        <input type="date" class="form-control" id="week_start" name="week_start" 
                               value="<?php echo date('Y-m-d', strtotime('monday this week')); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="week_end" class="form-label">Week End Date</label>
                        <input type="date" class="form-control" id="week_end" name="week_end" 
                               value="<?php echo date('Y-m-d', strtotime('sunday this week')); ?>" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <strong>Note:</strong> This will generate all weekly documents including:
                            <ul class="mb-0 mt-2">
                                <li>Weekly Ledger Report</li>
                                <li>Master Weekly Binder</li>
                                <li>Project Financial Sheets</li>
                                <li>Staff Performance Reports</li>
                            </ul>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Reports</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-set week end date when week start changes
document.getElementById('week_start').addEventListener('change', function() {
    const startDate = new Date(this.value);
    const endDate = new Date(startDate);
    endDate.setDate(startDate.getDate() + 6);
    
    const weekEndInput = document.getElementById('week_end');
    weekEndInput.value = endDate.toISOString().split('T')[0];
});
</script>

<?php include 'includes/footer.php'; ?>
