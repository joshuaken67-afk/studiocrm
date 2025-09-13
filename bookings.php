<?php
/**
 * Bookings Management Page
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
        
        if ($action == 'add') {
            $data = [
                'client_id' => $_POST['client_id'],
                'service' => $_POST['service'],
                'booking_date' => $_POST['booking_date'],
                'duration' => $_POST['duration'],
                'assigned_staff' => $_POST['assigned_staff'] ?: null,
                'notes' => $_POST['notes'],
                'created_by' => $_SESSION['user_id']
            ];
            
            try {
                $bookingId = $db->insert('bookings', $data);
                $auth->logAction($_SESSION['user_id'], 'create', 'bookings', $bookingId, null, $data);
                $message = 'Booking created successfully';
            } catch (Exception $e) {
                $error = 'Error creating booking: ' . $e->getMessage();
            }
        } elseif ($action == 'update') {
            $bookingId = $_POST['booking_id'];
            $data = [
                'service' => $_POST['service'],
                'booking_date' => $_POST['booking_date'],
                'duration' => $_POST['duration'],
                'assigned_staff' => $_POST['assigned_staff'] ?: null,
                'status' => $_POST['status'],
                'notes' => $_POST['notes']
            ];
            
            $oldBooking = $db->fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
            $db->update('bookings', $data, 'id = ?', [$bookingId]);
            $auth->logAction($_SESSION['user_id'], 'update', 'bookings', $bookingId, $oldBooking, $data);
            $message = 'Booking updated successfully';
        }
    }
}

// Get bookings with filters
$clientId = $_GET['client_id'] ?? '';
$status = $_GET['status'] ?? '';
$date = $_GET['date'] ?? '';
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * RECORDS_PER_PAGE;

$whereConditions = [];
$params = [];

if ($clientId) {
    $whereConditions[] = "b.client_id = ?";
    $params[] = $clientId;
}

if ($status) {
    $whereConditions[] = "b.status = ?";
    $params[] = $status;
}

if ($date) {
    $whereConditions[] = "DATE(b.booking_date) = ?";
    $params[] = $date;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$bookings = $db->fetchAll("
    SELECT b.*, c.name as client_name, c.email as client_email, c.phone_number,
           u.name as staff_name, creator.name as created_by_name
    FROM bookings b
    JOIN clients c ON b.client_id = c.id
    LEFT JOIN users u ON b.assigned_staff = u.id
    LEFT JOIN users creator ON b.created_by = creator.id
    $whereClause
    ORDER BY b.booking_date DESC, b.id DESC
    LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset
", $params);

$totalBookings = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM bookings b 
    JOIN clients c ON b.client_id = c.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalBookings / RECORDS_PER_PAGE);

// Get data for dropdowns
$clients = $db->fetchAll("SELECT id, name, email FROM clients WHERE status = 'active' ORDER BY name");
$staff = $db->fetchAll("SELECT id, name FROM users WHERE role IN ('staff', 'manager') AND status = 'active' ORDER BY name");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Bookings</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                    <i class="bi bi-plus"></i> Add Booking
                </button>
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

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select name="client_id" class="form-select">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="scheduled" <?= $status == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="no-show" <?= $status == 'no-show' ? 'selected' : '' ?>>No-show</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary">Filter</button>
                            <a href="bookings.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="export.php?type=bookings<?= $clientId ? '&client_id=' . $clientId : '' ?><?= $status ? '&status=' . $status : '' ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Calendar View Toggle -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="view" id="listView" checked>
                        <label class="btn btn-outline-primary" for="listView">
                            <i class="bi bi-list"></i> List View
                        </label>
                        
                        <input type="radio" class="btn-check" name="view" id="calendarView">
                        <label class="btn btn-outline-primary" for="calendarView">
                            <i class="bi bi-calendar"></i> Calendar View
                        </label>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div id="listViewContent" class="card">
                <div class="card-body">
                    <?php if (empty($bookings)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x display-1 text-muted"></i>
                            <h4 class="mt-3">No Bookings Found</h4>
                            <p class="text-muted">Start by creating a booking for your clients.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Staff</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($booking['client_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($booking['client_email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($booking['service']) ?></td>
                                        <td>
                                            <div><?= date('M j, Y', strtotime($booking['booking_date'])) ?></div>
                                            <small class="text-muted"><?= date('g:i A', strtotime($booking['booking_date'])) ?></small>
                                        </td>
                                        <td><?= $booking['duration'] ?> min</td>
                                        <td><?= $booking['staff_name'] ? htmlspecialchars($booking['staff_name']) : '<span class="text-muted">Unassigned</span>' ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $booking['status'] == 'scheduled' ? 'primary' : 
                                                ($booking['status'] == 'completed' ? 'success' : 
                                                ($booking['status'] == 'cancelled' ? 'danger' : 'warning')) 
                                            ?>">
                                                <?= ucfirst($booking['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="editBooking(<?= htmlspecialchars(json_encode($booking)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="projects.php?client_id=<?= $booking['client_id'] ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-folder"></i>
                                                </a>
                                            </div>
                                        </td>
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
                                    <a class="page-link" href="?page=<?= $i ?><?= $clientId ? '&client_id=' . $clientId : '' ?><?= $status ? '&status=' . $status : '' ?><?= $date ? '&date=' . $date : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Calendar View (Simple) -->
            <div id="calendarViewContent" class="card" style="display: none;">
                <div class="card-body">
                    <div class="row">
                        <?php
                        // Group bookings by date for calendar view
                        $bookingsByDate = [];
                        foreach ($bookings as $booking) {
                            $date = date('Y-m-d', strtotime($booking['booking_date']));
                            $bookingsByDate[$date][] = $booking;
                        }
                        
                        // Show next 7 days
                        for ($i = 0; $i < 7; $i++) {
                            $currentDate = date('Y-m-d', strtotime("+$i days"));
                            $dayBookings = $bookingsByDate[$currentDate] ?? [];
                        ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><?= date('D, M j', strtotime($currentDate)) ?></h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($dayBookings)): ?>
                                        <p class="text-muted small">No bookings</p>
                                    <?php else: ?>
                                        <?php foreach ($dayBookings as $booking): ?>
                                        <div class="mb-2 p-2 border rounded">
                                            <div class="fw-bold small"><?= date('g:i A', strtotime($booking['booking_date'])) ?></div>
                                            <div class="small"><?= htmlspecialchars($booking['client_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($booking['service']) ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit Booking Modal -->
<div class="modal fade" id="addBookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="bookingForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add" id="formAction">
                    <input type="hidden" name="booking_id" id="bookingId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Client *</label>
                                <select name="client_id" id="clientSelect" class="form-select" required>
                                    <option value="">Select a client...</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Service *</label>
                                <input type="text" name="service" id="serviceInput" class="form-control" required placeholder="e.g., Consultation, Photo Shoot, Design Review">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Date & Time *</label>
                                <input type="datetime-local" name="booking_date" id="dateInput" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Duration (minutes)</label>
                                <select name="duration" id="durationSelect" class="form-select">
                                    <option value="30">30 minutes</option>
                                    <option value="60" selected>1 hour</option>
                                    <option value="90">1.5 hours</option>
                                    <option value="120">2 hours</option>
                                    <option value="180">3 hours</option>
                                    <option value="240">4 hours</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Assigned Staff</label>
                                <select name="assigned_staff" id="staffSelect" class="form-select">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staff as $member): ?>
                                    <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" id="statusDiv" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="statusSelect" class="form-select">
                                    <option value="scheduled">Scheduled</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no-show">No-show</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="notesInput" class="form-control" rows="3" placeholder="Special requirements, preparation notes, or other details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// View toggle functionality
document.getElementById('listView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('listViewContent').style.display = 'block';
        document.getElementById('calendarViewContent').style.display = 'none';
    }
});

document.getElementById('calendarView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('listViewContent').style.display = 'none';
        document.getElementById('calendarViewContent').style.display = 'block';
    }
});

function editBooking(booking) {
    document.getElementById('modalTitle').textContent = 'Edit Booking';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Booking';
    document.getElementById('bookingId').value = booking.id;
    document.getElementById('clientSelect').value = booking.client_id;
    document.getElementById('clientSelect').disabled = true;
    document.getElementById('serviceInput').value = booking.service;
    document.getElementById('dateInput').value = booking.booking_date.replace(' ', 'T');
    document.getElementById('durationSelect').value = booking.duration;
    document.getElementById('staffSelect').value = booking.assigned_staff || '';
    document.getElementById('statusSelect').value = booking.status;
    document.getElementById('notesInput').value = booking.notes || '';
    document.getElementById('statusDiv').style.display = 'block';
    
    new bootstrap.Modal(document.getElementById('addBookingModal')).show();
}

// Reset form when modal is closed
document.getElementById('addBookingModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('bookingForm').reset();
    document.getElementById('modalTitle').textContent = 'Add Booking';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Add Booking';
    document.getElementById('bookingId').value = '';
    document.getElementById('clientSelect').disabled = false;
    document.getElementById('statusDiv').style.display = 'none';
    document.getElementById('durationSelect').value = '60';
});
</script>

<?php include 'includes/footer.php'; ?>
