<?php
/**
 * Generate Branded PDF Report
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

// Get parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'overview';

// Get analytics data (simplified for report)
$analytics = [
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?", [$startDate, $endDate])['total'],
    'new_clients' => $db->fetchOne("SELECT COUNT(*) as count FROM clients WHERE DATE(created_at) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    'completed_projects' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'completed' AND DATE(updated_at) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    'total_bookings' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    'top_clients' => $db->fetchAll("
        SELECT c.name, SUM(p.amount) as total_paid
        FROM clients c
        JOIN payments p ON c.id = p.client_id
        WHERE DATE(p.payment_date) BETWEEN ? AND ?
        GROUP BY c.id, c.name
        ORDER BY total_paid DESC
        LIMIT 5
    ", [$startDate, $endDate]),
    'staff_performance' => $db->fetchAll("
        SELECT u.name, COUNT(DISTINCT p.id) as projects_assigned
        FROM users u
        LEFT JOIN projects p ON u.id = p.assigned_staff AND DATE(p.created_at) BETWEEN ? AND ?
        WHERE u.role IN ('staff', 'manager') AND u.status = 'active'
        GROUP BY u.id, u.name
        ORDER BY projects_assigned DESC
        LIMIT 5
    ", [$startDate, $endDate])
];

// Log the report generation
$auth->logAction($_SESSION['user_id'], 'generate_report', 'reports', 0, null, ['type' => $reportType, 'period' => $startDate . ' to ' . $endDate]);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= COMPANY_NAME ?> - <?= ucfirst($reportType) ?> Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .header .subtitle {
            color: #666;
            font-size: 16px;
            margin-top: 5px;
        }
        .report-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .report-info h3 {
            margin: 0 0 10px 0;
            color: #007bff;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .metric-card h4 {
            color: #007bff;
            margin: 0 0 10px 0;
            font-size: 14px;
            text-transform: uppercase;
        }
        .metric-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h3 {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .data-table th,
        .data-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .data-table th {
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background: #f8f9fa;
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
            border-top: 1px solid #dee2e6;
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
            Print Report
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close
        </button>
    </div>

    <div class="header">
        <h1><?= COMPANY_NAME ?></h1>
        <div class="subtitle"><?= ucfirst($reportType) ?> Report</div>
        <div class="subtitle"><?= date('F j, Y', strtotime($startDate)) ?> - <?= date('F j, Y', strtotime($endDate)) ?></div>
    </div>

    <div class="report-info">
        <h3>Report Summary</h3>
        <p><strong>Report Type:</strong> <?= ucfirst($reportType) ?> Analysis</p>
        <p><strong>Period:</strong> <?= date('F j, Y', strtotime($startDate)) ?> to <?= date('F j, Y', strtotime($endDate)) ?></p>
        <p><strong>Generated:</strong> <?= date('F j, Y g:i A') ?></p>
        <p><strong>Generated By:</strong> <?= htmlspecialchars($auth->getCurrentUser()['name']) ?></p>
    </div>

    <!-- Key Metrics -->
    <div class="section">
        <h3>Key Performance Indicators</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <h4>Total Revenue</h4>
                <div class="value">$<?= number_format($analytics['total_revenue'], 2) ?></div>
            </div>
            <div class="metric-card">
                <h4>New Clients</h4>
                <div class="value"><?= $analytics['new_clients'] ?></div>
            </div>
            <div class="metric-card">
                <h4>Completed Projects</h4>
                <div class="value"><?= $analytics['completed_projects'] ?></div>
            </div>
            <div class="metric-card">
                <h4>Total Bookings</h4>
                <div class="value"><?= $analytics['total_bookings'] ?></div>
            </div>
        </div>
    </div>

    <!-- Top Clients -->
    <?php if (!empty($analytics['top_clients'])): ?>
    <div class="section">
        <h3>Top Clients by Revenue</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analytics['top_clients'] as $client): ?>
                <tr>
                    <td><?= htmlspecialchars($client['name']) ?></td>
                    <td>$<?= number_format($client['total_paid'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Staff Performance -->
    <?php if (!empty($analytics['staff_performance'])): ?>
    <div class="section">
        <h3>Staff Performance</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Projects Assigned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analytics['staff_performance'] as $staff): ?>
                <tr>
                    <td><?= htmlspecialchars($staff['name']) ?></td>
                    <td><?= $staff['projects_assigned'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Analysis & Recommendations -->
    <div class="section">
        <h3>Analysis & Recommendations</h3>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">
            <?php
            $recommendations = [];
            
            if ($analytics['total_revenue'] > 0) {
                $recommendations[] = "Revenue performance is positive with $" . number_format($analytics['total_revenue'], 2) . " generated in the selected period.";
            } else {
                $recommendations[] = "Consider reviewing pricing strategy and sales processes to improve revenue generation.";
            }
            
            if ($analytics['new_clients'] > 0) {
                $recommendations[] = "Client acquisition is active with " . $analytics['new_clients'] . " new clients added.";
            } else {
                $recommendations[] = "Focus on marketing and lead generation to attract new clients.";
            }
            
            if ($analytics['completed_projects'] > 0) {
                $recommendations[] = "Project completion rate shows " . $analytics['completed_projects'] . " projects successfully delivered.";
            }
            
            $recommendations[] = "Continue monitoring key metrics and adjust strategies based on performance trends.";
            ?>
            
            <?php foreach ($recommendations as $recommendation): ?>
            <p>• <?= $recommendation ?></p>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Prepared By
            </div>
            <p><?= htmlspecialchars($auth->getCurrentUser()['name']) ?></p>
            <p>Date: <?= date('F j, Y') ?></p>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Reviewed By
            </div>
            <p>Management</p>
            <p>Date: _______________</p>
        </div>
    </div>

    <div class="footer">
        <p><strong><?= COMPANY_NAME ?></strong></p>
        <p><?= COMPANY_ADDRESS ?> | <?= COMPANY_PHONE ?> | <?= COMPANY_EMAIL ?></p>
        <p>This report contains confidential business information. Distribution should be limited to authorized personnel only.</p>
    </div>
</body>
</html>
