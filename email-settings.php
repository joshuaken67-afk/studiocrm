<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/email-notifications.php';

// Check authentication and admin role
if (!isLoggedIn() || getCurrentUser()['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$current_user = getCurrentUser();
$email_system = new EmailNotificationSystem($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'test_email':
                try {
                    if ($email_system->testEmailConfiguration()) {
                        $success_message = "Test email sent successfully! Check your inbox.";
                    } else {
                        $error_message = "Failed to send test email. Check your SMTP configuration.";
                    }
                } catch (Exception $e) {
                    $error_message = "Error sending test email: " . $e->getMessage();
                }
                break;
                
            case 'process_pending':
                try {
                    $results = $email_system->processPendingNotifications();
                    $success_message = "Processed {$results['processed']} emails. {$results['failed']} failed.";
                } catch (Exception $e) {
                    $error_message = "Error processing emails: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get notification statistics
$notification_stats = $email_system->getNotificationStats(30);

// Get recent notifications
$recent_sql = "SELECT * FROM email_notifications 
               ORDER BY created_at DESC 
               LIMIT 20";
$recent_stmt = $pdo->query($recent_sql);
$recent_notifications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending count
$pending_sql = "SELECT COUNT(*) FROM email_notifications WHERE status = 'pending'";
$pending_count = $pdo->query($pending_sql)->fetchColumn();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Email Notification Settings</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="test_email">
                        <button type="submit" class="btn btn-outline-primary">Test Email</button>
                    </form>
                    <form method="POST" class="d-inline ms-2">
                        <input type="hidden" name="action" value="process_pending">
                        <button type="submit" class="btn btn-primary">Process Pending (<?php echo $pending_count; ?>)</button>
                    </form>
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

            <!-- Email Configuration -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">SMTP Configuration</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Host:</strong> <?php echo $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com'; ?></p>
                            <p><strong>Port:</strong> <?php echo $_ENV['SMTP_PORT'] ?? '587'; ?></p>
                            <p><strong>Username:</strong> <?php echo $_ENV['SMTP_USERNAME'] ?? 'Not configured'; ?></p>
                            <p><strong>From Email:</strong> <?php echo $_ENV['FROM_EMAIL'] ?? 'noreply@148studios.com'; ?></p>
                            <p><strong>From Name:</strong> <?php echo $_ENV['FROM_NAME'] ?? '148 Studios Management System'; ?></p>
                            
                            <div class="alert alert-info mt-3">
                                <small>
                                    <strong>Configuration:</strong> Set these environment variables:
                                    <ul class="mb-0 mt-2">
                                        <li>SMTP_HOST</li>
                                        <li>SMTP_PORT</li>
                                        <li>SMTP_USERNAME</li>
                                        <li>SMTP_PASSWORD</li>
                                        <li>FROM_EMAIL</li>
                                        <li>FROM_NAME</li>
                                    </ul>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Notification Types</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-envelope text-primary"></i> Invoice Sent</li>
                                <li><i class="bi bi-cash-coin text-success"></i> Payment Received</li>
                                <li><i class="bi bi-exclamation-triangle text-warning"></i> Overdue Reminders</li>
                                <li><i class="bi bi-file-earmark-text text-info"></i> Weekly Reports</li>
                                <li><i class="bi bi-bell text-danger"></i> System Alerts</li>
                            </ul>
                            
                            <div class="mt-3">
                                <h6>Automation Schedule:</h6>
                                <small class="text-muted">
                                    <strong>Email Processing:</strong> Every 5 minutes<br>
                                    <strong>Overdue Reminders:</strong> Daily at 9:00 AM<br>
                                    <strong>Weekly Reports:</strong> Fridays at 6:00 PM
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Email Statistics (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Notification Type</th>
                                            <th>Sent</th>
                                            <th>Pending</th>
                                            <th>Failed</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stats_summary = [];
                                        foreach ($notification_stats as $stat) {
                                            $type = $stat['notification_type'];
                                            if (!isset($stats_summary[$type])) {
                                                $stats_summary[$type] = ['sent' => 0, 'pending' => 0, 'failed' => 0];
                                            }
                                            $stats_summary[$type][$stat['status']] = $stat['count'];
                                        }
                                        
                                        foreach ($stats_summary as $type => $counts):
                                            $total = array_sum($counts);
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $type)); ?></td>
                                            <td><span class="badge bg-success"><?php echo $counts['sent'] ?? 0; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $counts['pending'] ?? 0; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $counts['failed'] ?? 0; ?></span></td>
                                            <td><strong><?php echo $total; ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="card">
                <div class="card-header">
                    <h5>Recent Email Notifications</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_notifications as $notification): ?>
                                <tr>
                                    <td><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($notification['recipient_email']); ?></td>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($notification['subject']); ?>">
                                            <?php echo htmlspecialchars(substr($notification['subject'], 0, 40)) . (strlen($notification['subject']) > 40 ? '...' : ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $notification['status'] === 'sent' ? 'success' : 
                                                ($notification['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($notification['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($notification['sent_at']): ?>
                                            <?php echo date('M j, g:i A', strtotime($notification['sent_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
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

<?php include 'includes/footer.php'; ?>
