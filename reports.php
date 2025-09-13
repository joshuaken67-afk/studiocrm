<?php
/**
 * Reports and Analytics Page
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();

// Get date range from filters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$reportType = $_GET['report_type'] ?? 'overview';

// Validate dates
if (strtotime($startDate) > strtotime($endDate)) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
}

// Get comprehensive analytics data
$analytics = [
    // Client Analytics
    'total_clients' => $db->fetchOne("SELECT COUNT(*) as count FROM clients WHERE status = 'active'")['count'],
    'new_clients' => $db->fetchOne("SELECT COUNT(*) as count FROM clients WHERE DATE(created_at) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    'clients_by_source' => $db->fetchAll("SELECT source, COUNT(*) as count FROM clients WHERE source IS NOT NULL AND DATE(created_at) BETWEEN ? AND ? GROUP BY source ORDER BY count DESC", [$startDate, $endDate]),
    
    // Staff Analytics
    'active_staff' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role IN ('staff', 'manager') AND status = 'active'")['count'],
    'staff_performance' => $db->fetchAll("
        SELECT u.name, u.role,
               COUNT(DISTINCT p.id) as projects_assigned,
               COUNT(DISTINCT b.id) as bookings_handled,
               COUNT(DISTINCT pay.id) as payments_recorded
        FROM users u
        LEFT JOIN projects p ON u.id = p.assigned_staff AND DATE(p.created_at) BETWEEN ? AND ?
        LEFT JOIN bookings b ON u.id = b.assigned_staff AND DATE(b.created_at) BETWEEN ? AND ?
        LEFT JOIN payments pay ON u.id = pay.recorded_by AND DATE(pay.created_at) BETWEEN ? AND ?
        WHERE u.role IN ('staff', 'manager') AND u.status = 'active'
        GROUP BY u.id, u.name, u.role
        ORDER BY projects_assigned DESC, bookings_handled DESC
    ", [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate]),
    
    // Project Analytics
    'total_projects' => $db->fetchOne("SELECT COUNT(*) as count FROM projects")['count'],
    'projects_by_status' => $db->fetchAll("SELECT status, COUNT(*) as count FROM projects WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status", [$startDate, $endDate]),
    'completed_projects' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'completed' AND DATE(updated_at) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    
    // Booking Analytics
    'total_bookings' => $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    'bookings_by_status' => $db->fetchAll("SELECT status, COUNT(*) as count FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ? GROUP BY status", [$startDate, $endDate]),
    'bookings_by_service' => $db->fetchAll("SELECT service, COUNT(*) as count FROM bookings WHERE DATE(booking_date) BETWEEN ? AND ? GROUP BY service ORDER BY count DESC LIMIT 10", [$startDate, $endDate]),
    
    // Financial Analytics
    'total_revenue' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?", [$startDate, $endDate])['total'],
    'total_invoiced' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE status != 'draft' AND DATE(created_at) BETWEEN ? AND ?", [$startDate, $endDate])['total'],
    'pending_payments' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM invoices WHERE status IN ('sent', 'overdue')")['total'],
    'revenue_by_month' => $db->fetchAll("
        SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as revenue
        FROM payments 
        WHERE DATE(payment_date) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month
    ", [$startDate, $endDate]),
    'payment_methods' => $db->fetchAll("SELECT payment_method, COUNT(*) as count, SUM(amount) as total FROM payments WHERE DATE(payment_date) BETWEEN ? AND ? GROUP BY payment_method", [$startDate, $endDate]),
    
    // Top Clients
    'top_clients_by_revenue' => $db->fetchAll("
        SELECT c.name, c.email, SUM(p.amount) as total_paid, COUNT(p.id) as payment_count
        FROM clients c
        JOIN payments p ON c.id = p.client_id
        WHERE DATE(p.payment_date) BETWEEN ? AND ?
        GROUP BY c.id, c.name, c.email
        ORDER BY total_paid DESC
        LIMIT 10
    ", [$startDate, $endDate]),
    
    'top_clients_by_projects' => $db->fetchAll("
        SELECT c.name, c.email, COUNT(pr.id) as project_count
        FROM clients c
        JOIN projects pr ON c.id = pr.client_id
        WHERE DATE(pr.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.name, c.email
        ORDER BY project_count DESC
        LIMIT 10
    ", [$startDate, $endDate])
];

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Reports & Analytics</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" onclick="generateBrandedReport()">
                        <i class="bi bi-file-earmark-pdf"></i> Generate Branded Report
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i> Export Data
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="export.php?type=clients">Clients CSV</a></li>
                            <li><a class="dropdown-item" href="export.php?type=bookings">Bookings CSV</a></li>
                            <li><a class="dropdown-item" href="export.php?type=projects">Projects CSV</a></li>
                            <li><a class="dropdown-item" href="export.php?type=payments">Payments CSV</a></li>
                            <li><a class="dropdown-item" href="export.php?type=invoices">Invoices CSV</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="report_type" class="form-select">
                                <option value="overview" <?= $reportType == 'overview' ? 'selected' : '' ?>>Overview</option>
                                <option value="financial" <?= $reportType == 'financial' ? 'selected' : '' ?>>Financial</option>
                                <option value="staff" <?= $reportType == 'staff' ? 'selected' : '' ?>>Staff Performance</option>
                                <option value="clients" <?= $reportType == 'clients' ? 'selected' : '' ?>>Client Analysis</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">Update Report</button>
                                <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?= number_format($analytics['total_revenue'], 2) ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">New Clients</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $analytics['new_clients'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-person-plus fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Completed Projects</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $analytics['completed_projects'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Bookings</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $analytics['total_bookings'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-calendar-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Content Based on Type -->
            <?php if ($reportType == 'overview' || $reportType == 'financial'): ?>
            <div class="row mb-4">
                <!-- Revenue Chart -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Revenue by Month</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['revenue_by_month'])): ?>
                            <canvas id="revenueChart" width="400" height="200"></canvas>
                            <?php else: ?>
                            <p class="text-muted text-center">No revenue data for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Payment Methods</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['payment_methods'])): ?>
                                <?php foreach ($analytics['payment_methods'] as $method): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= ucfirst(str_replace('_', ' ', $method['payment_method'])) ?></span>
                                    <div>
                                        <span class="badge bg-primary"><?= $method['count'] ?></span>
                                        <span class="fw-bold">$<?= number_format($method['total'], 2) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">No payment data for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($reportType == 'overview' || $reportType == 'staff'): ?>
            <!-- Staff Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Staff Performance</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Staff Member</th>
                                            <th>Role</th>
                                            <th>Projects Assigned</th>
                                            <th>Bookings Handled</th>
                                            <th>Payments Recorded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analytics['staff_performance'] as $staff): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($staff['name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $staff['role'] == 'manager' ? 'warning' : 'info' ?>">
                                                    <?= ucfirst($staff['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= $staff['projects_assigned'] ?></td>
                                            <td><?= $staff['bookings_handled'] ?></td>
                                            <td><?= $staff['payments_recorded'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($reportType == 'overview' || $reportType == 'clients'): ?>
            <div class="row mb-4">
                <!-- Top Clients by Revenue -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Top Clients by Revenue</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['top_clients_by_revenue'])): ?>
                                <?php foreach ($analytics['top_clients_by_revenue'] as $client): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($client['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($client['email']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success">$<?= number_format($client['total_paid'], 2) ?></div>
                                        <small class="text-muted"><?= $client['payment_count'] ?> payment<?= $client['payment_count'] != 1 ? 's' : '' ?></small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">No client revenue data for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Client Sources -->
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">New Clients by Source</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['clients_by_source'])): ?>
                                <?php foreach ($analytics['clients_by_source'] as $source): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= htmlspecialchars($source['source']) ?></span>
                                    <span class="badge bg-primary"><?= $source['count'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">No client source data for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Project and Booking Status -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Projects by Status</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['projects_by_status'])): ?>
                                <?php foreach ($analytics['projects_by_status'] as $status): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= ucwords(str_replace('-', ' ', $status['status'])) ?></span>
                                    <span class="badge bg-<?= 
                                        $status['status'] == 'completed' ? 'success' : 
                                        ($status['status'] == 'in-progress' ? 'primary' : 
                                        ($status['status'] == 'on-hold' ? 'warning' : 'secondary')) 
                                    ?>"><?= $status['count'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">No project data for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Bookings by Status</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($analytics['bookings_by_status'])): ?>
                                <?php foreach ($analytics['bookings_by_status'] as $status): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span><?= ucfirst($status['status']) ?></span>
                                    <span class="badge bg-<?= 
                                        $status['status'] == 'completed' ? 'success' : 
                                        ($status['status'] == 'scheduled' ? 'primary' : 
                                        ($status['status'] == 'cancelled' ? 'danger' : 'warning')) 
                                    ?>"><?= $status['count'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p class="text-muted">No booking data for selected period</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Services -->
            <?php if (!empty($analytics['bookings_by_service'])): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Most Popular Services</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($analytics['bookings_by_service'] as $service): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                        <span><?= htmlspecialchars($service['service']) ?></span>
                                        <span class="badge bg-info"><?= $service['count'] ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Chart.js for Revenue Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($analytics['revenue_by_month'])): ?>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: [<?= implode(',', array_map(function($item) { return '"' . date('M Y', strtotime($item['month'] . '-01')) . '"'; }, $analytics['revenue_by_month'])) ?>],
        datasets: [{
            label: 'Revenue',
            data: [<?= implode(',', array_column($analytics['revenue_by_month'], 'revenue')) ?>],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: $' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});
<?php endif; ?>

function generateBrandedReport() {
    const startDate = '<?= $startDate ?>';
    const endDate = '<?= $endDate ?>';
    const reportType = '<?= $reportType ?>';
    
    window.open(`generate_branded_report.php?start_date=${startDate}&end_date=${endDate}&report_type=${reportType}`, '_blank');
}
</script>

<?php include 'includes/footer.php'; ?>
