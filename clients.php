<?php
/**
 * Client Management Page
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
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phone_number'],
                'service_interest' => $_POST['service_interest'],
                'source' => $_POST['source'],
                'notes' => $_POST['notes']
            ];
            
            try {
                $clientId = $db->insert('clients', $data);
                $auth->logAction($_SESSION['user_id'], 'create', 'clients', $clientId, null, $data);
                $message = 'Client added successfully';
            } catch (Exception $e) {
                $error = 'Error adding client: ' . $e->getMessage();
            }
        } elseif ($action == 'update') {
            $clientId = $_POST['client_id'];
            $data = [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phone_number'],
                'service_interest' => $_POST['service_interest'],
                'source' => $_POST['source'],
                'notes' => $_POST['notes']
            ];
            
            $oldClient = $db->fetchOne("SELECT * FROM clients WHERE id = ?", [$clientId]);
            $db->update('clients', $data, 'id = ?', [$clientId]);
            $auth->logAction($_SESSION['user_id'], 'update', 'clients', $clientId, $oldClient, $data);
            $message = 'Client updated successfully';
        }
    }
}

// Get clients list with pagination
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$offset = ($page - 1) * RECORDS_PER_PAGE;

$whereClause = '';
$params = [];
if ($search) {
    $whereClause = "WHERE name LIKE ? OR email LIKE ? OR phone_number LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$clients = $db->fetchAll("
    SELECT * FROM clients 
    $whereClause 
    ORDER BY created_at DESC 
    LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset
", $params);

$totalClients = $db->fetchOne("SELECT COUNT(*) as count FROM clients $whereClause", $params)['count'];
$totalPages = ceil($totalClients / RECORDS_PER_PAGE);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Client Management</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i class="bi bi-plus"></i> Add Client
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

            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" name="search" class="form-control" placeholder="Search clients..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i> Search
                            </button>
                            <a href="clients.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="export.php?type=clients" class="btn btn-success">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Service Interest</th>
                                    <th>Source</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= htmlspecialchars($client['name']) ?></td>
                                    <td><?= htmlspecialchars($client['email']) ?></td>
                                    <td><?= htmlspecialchars($client['phone_number']) ?></td>
                                    <td><?= htmlspecialchars($client['service_interest']) ?></td>
                                    <td><?= htmlspecialchars($client['source']) ?></td>
                                    <td><?= date('M j, Y', strtotime($client['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" onclick="editClient(<?= htmlspecialchars(json_encode($client)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="clarity-forms.php?client_id=<?= $client['id'] ?>" class="btn btn-outline-success">
                                                <i class="bi bi-clipboard-check"></i>
                                            </a>
                                            <a href="bookings.php?client_id=<?= $client['id'] ?>" class="btn btn-outline-info">
                                                <i class="bi bi-calendar"></i>
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
                                <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="clientForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add" id="formAction">
                    <input type="hidden" name="client_id" id="clientId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="name" id="clientName" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" id="clientEmail" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone_number" id="clientPhone" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Source</label>
                                <select name="source" id="clientSource" class="form-select">
                                    <option value="">Select source...</option>
                                    <option value="Website">Website</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Social Media">Social Media</option>
                                    <option value="Google">Google</option>
                                    <option value="Walk-in">Walk-in</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Service Interest</label>
                        <input type="text" name="service_interest" id="clientService" class="form-control" placeholder="What services are they interested in?">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="clientNotes" class="form-control" rows="3" placeholder="Additional notes about the client..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editClient(client) {
    document.getElementById('modalTitle').textContent = 'Edit Client';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Client';
    document.getElementById('clientId').value = client.id;
    document.getElementById('clientName').value = client.name;
    document.getElementById('clientEmail').value = client.email;
    document.getElementById('clientPhone').value = client.phone_number;
    document.getElementById('clientService').value = client.service_interest || '';
    document.getElementById('clientSource').value = client.source || '';
    document.getElementById('clientNotes').value = client.notes || '';
    
    new bootstrap.Modal(document.getElementById('addClientModal')).show();
}

// Reset form when modal is closed
document.getElementById('addClientModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('clientForm').reset();
    document.getElementById('modalTitle').textContent = 'Add Client';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Add Client';
    document.getElementById('clientId').value = '';
});
</script>

<?php include 'includes/footer.php'; ?>
