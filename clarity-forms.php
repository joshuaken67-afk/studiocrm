<?php
/**
 * Clarity Forms Management Page
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
                'budget' => $_POST['budget'] ?: null,
                'timeline' => $_POST['timeline'],
                'preferred_contact' => $_POST['preferred_contact'],
                'notes' => $_POST['notes']
            ];
            
            try {
                $formId = $db->insert('clarity_forms', $data);
                $auth->logAction($_SESSION['user_id'], 'create', 'clarity_forms', $formId, null, $data);
                $message = 'Clarity form added successfully';
            } catch (Exception $e) {
                $error = 'Error adding clarity form: ' . $e->getMessage();
            }
        } elseif ($action == 'update') {
            $formId = $_POST['form_id'];
            $data = [
                'budget' => $_POST['budget'] ?: null,
                'timeline' => $_POST['timeline'],
                'preferred_contact' => $_POST['preferred_contact'],
                'notes' => $_POST['notes']
            ];
            
            $oldForm = $db->fetchOne("SELECT * FROM clarity_forms WHERE id = ?", [$formId]);
            $db->update('clarity_forms', $data, 'id = ?', [$formId]);
            $auth->logAction($_SESSION['user_id'], 'update', 'clarity_forms', $formId, $oldForm, $data);
            $message = 'Clarity form updated successfully';
        }
    }
}

// Get clarity forms with client information
$clientId = $_GET['client_id'] ?? '';
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * RECORDS_PER_PAGE;

$whereClause = '';
$params = [];
if ($clientId) {
    $whereClause = "WHERE cf.client_id = ?";
    $params = [$clientId];
}

$clarityForms = $db->fetchAll("
    SELECT cf.*, c.name as client_name, c.email as client_email, c.phone_number
    FROM clarity_forms cf
    JOIN clients c ON cf.client_id = c.id
    $whereClause
    ORDER BY cf.created_at DESC
    LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset
", $params);

$totalForms = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM clarity_forms cf 
    JOIN clients c ON cf.client_id = c.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalForms / RECORDS_PER_PAGE);

// Get all clients for dropdown
$clients = $db->fetchAll("SELECT id, name, email FROM clients WHERE status = 'active' ORDER BY name");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Clarity Forms</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClarityModal">
                    <i class="bi bi-plus"></i> Add Clarity Form
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

            <!-- Filter -->
            <?php if (!$clientId): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <select name="client_id" class="form-select">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $clientId == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary">Filter</button>
                            <a href="clarity-forms.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="export.php?type=clarity_forms<?= $clientId ? '&client_id=' . $clientId : '' ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($clarityForms)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-1 text-muted"></i>
                            <h4 class="mt-3">No Clarity Forms Found</h4>
                            <p class="text-muted">Start by adding a clarity form for your clients.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($clarityForms as $form): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-left-primary">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="card-title mb-0"><?= htmlspecialchars($form['client_name']) ?></h6>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="editClarityForm(<?= htmlspecialchars(json_encode($form)) ?>)">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="bookings.php?client_id=<?= $form['client_id'] ?>">
                                                        <i class="bi bi-calendar"></i> Create Booking
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted">Budget:</small>
                                            <div class="fw-bold">
                                                <?= $form['budget'] ? '$' . number_format($form['budget'], 2) : 'Not specified' ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted">Timeline:</small>
                                            <div><?= htmlspecialchars($form['timeline']) ?: 'Not specified' ?></div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <small class="text-muted">Preferred Contact:</small>
                                            <div>
                                                <span class="badge bg-info">
                                                    <?= ucfirst($form['preferred_contact']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if ($form['notes']): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Notes:</small>
                                            <div class="small"><?= nl2br(htmlspecialchars($form['notes'])) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3 pt-2 border-top">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> 
                                                <?= date('M j, Y g:i A', strtotime($form['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= $clientId ? '&client_id=' . $clientId : '' ?>"><?= $i ?></a>
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

<!-- Add/Edit Clarity Form Modal -->
<div class="modal fade" id="addClarityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="clarityForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Clarity Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add" id="formAction">
                    <input type="hidden" name="form_id" id="formId">
                    
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Budget</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="budget" id="budgetInput" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Timeline</label>
                                <select name="timeline" id="timelineSelect" class="form-select">
                                    <option value="">Select timeline...</option>
                                    <option value="ASAP">ASAP</option>
                                    <option value="1-2 weeks">1-2 weeks</option>
                                    <option value="1 month">1 month</option>
                                    <option value="2-3 months">2-3 months</option>
                                    <option value="3-6 months">3-6 months</option>
                                    <option value="6+ months">6+ months</option>
                                    <option value="Flexible">Flexible</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Preferred Contact Method</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="preferred_contact" value="email" id="contactEmail" checked>
                                    <label class="form-check-label" for="contactEmail">
                                        <i class="bi bi-envelope"></i> Email
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="preferred_contact" value="phone" id="contactPhone">
                                    <label class="form-check-label" for="contactPhone">
                                        <i class="bi bi-telephone"></i> Phone
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="preferred_contact" value="text" id="contactText">
                                    <label class="form-check-label" for="contactText">
                                        <i class="bi bi-chat"></i> Text
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="notesInput" class="form-control" rows="4" placeholder="Additional details about the project requirements, goals, or special considerations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Clarity Form</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editClarityForm(form) {
    document.getElementById('modalTitle').textContent = 'Edit Clarity Form';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Form';
    document.getElementById('formId').value = form.id;
    document.getElementById('clientSelect').value = form.client_id;
    document.getElementById('clientSelect').disabled = true;
    document.getElementById('budgetInput').value = form.budget || '';
    document.getElementById('timelineSelect').value = form.timeline || '';
    document.getElementById('notesInput').value = form.notes || '';
    
    // Set preferred contact method
    const contactRadios = document.querySelectorAll('input[name="preferred_contact"]');
    contactRadios.forEach(radio => {
        radio.checked = radio.value === form.preferred_contact;
    });
    
    new bootstrap.Modal(document.getElementById('addClarityModal')).show();
}

// Reset form when modal is closed
document.getElementById('addClarityModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('clarityForm').reset();
    document.getElementById('modalTitle').textContent = 'Add Clarity Form';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Add Clarity Form';
    document.getElementById('formId').value = '';
    document.getElementById('clientSelect').disabled = false;
    document.getElementById('contactEmail').checked = true;
});
</script>

<?php include 'includes/footer.php'; ?>
