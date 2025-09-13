<?php
/**
 * StudioCRM Dashboard
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$user = $auth->getCurrentUser();

// Get dashboard stats
$stats = [
    'total_clients' => $db->fetchOne("SELECT COUNT(*) as count FROM clients WHERE status = 'active'")['count'],
    'total_staff' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role IN ('staff', 'manager') AND status = 'active'")['count'],
    'active_projects' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status IN ('pending', 'in-progress')")['count'],
    'pending_payments' => $db->fetchOne("SELECT COUNT(*) as count FROM invoices WHERE status IN ('sent', 'overdue')")['count']
];

// Recent activities
$recent_clients = $db->fetchAll("SELECT * FROM clients ORDER BY created_at DESC LIMIT 5");
$recent_bookings = $db->fetchAll("
    SELECT b.*, c.name as client_name, u.name as staff_name 
    FROM bookings b 
    LEFT JOIN clients c ON b.client_id = c.id 
    LEFT JOIN users u ON b.assigned_staff = u.id 
    ORDER BY b.created_at DESC LIMIT 5
");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Active Clients</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_clients'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-people fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Staff Members</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_staff'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-person-badge fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Projects</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_projects'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-folder fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Payments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending_payments'] ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-credit-card fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Clients</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_clients)): ?>
                                <p class="text-muted">No clients yet.</p>
                            <?php else: ?>
                                <?php foreach ($recent_clients as $client): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bi bi-person text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($client['name']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($client['email']) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Bookings</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_bookings)): ?>
                                <p class="text-muted">No bookings yet.</p>
                            <?php else: ?>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="me-3">
                                            <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bi bi-calendar text-white"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($booking['service']) ?></div>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($booking['client_name']) ?> - 
                                                <?= date('M j, Y', strtotime($booking['booking_date'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
