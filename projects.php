<?php
/**
 * Projects Management Page
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
                'status' => 'pending',
                'assigned_staff' => $_POST['assigned_staff'] ?: null,
                'notes' => $_POST['notes']
            ];
            
            try {
                $projectId = $db->insert('projects', $data);
                $auth->logAction($_SESSION['user_id'], 'create', 'projects', $projectId, null, $data);
                $message = 'Project created successfully';
            } catch (Exception $e) {
                $error = 'Error creating project: ' . $e->getMessage();
            }
        } elseif ($action == 'update') {
            $projectId = $_POST['project_id'];
            $data = [
                'service' => $_POST['service'],
                'status' => $_POST['status'],
                'assigned_staff' => $_POST['assigned_staff'] ?: null,
                'deliverables' => $_POST['deliverables'],
                'notes' => $_POST['notes']
            ];
            
            $oldProject = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
            $db->update('projects', $data, 'id = ?', [$projectId]);
            $auth->logAction($_SESSION['user_id'], 'update', 'projects', $projectId, $oldProject, $data);
            $message = 'Project updated successfully';
        } elseif ($action == 'upload') {
            $projectId = $_POST['project_id'];
            
            if (isset($_FILES['files']) && $_FILES['files']['error'][0] !== UPLOAD_ERR_NO_FILE) {
                $uploadedFiles = [];
                $uploadDir = UPLOAD_PATH . 'projects/' . $projectId . '/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $fileName = $_FILES['files']['name'][$i];
                        $fileSize = $_FILES['files']['size'][$i];
                        $fileTmp = $_FILES['files']['tmp_name'][$i];
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Validate file
                        if ($fileSize > UPLOAD_MAX_SIZE) {
                            $error = "File $fileName is too large. Maximum size is " . (UPLOAD_MAX_SIZE / 1024 / 1024) . "MB";
                            break;
                        }
                        
                        if (!in_array($fileExt, UPLOAD_ALLOWED_TYPES)) {
                            $error = "File type $fileExt is not allowed for $fileName";
                            break;
                        }
                        
                        // Generate unique filename
                        $newFileName = time() . '_' . uniqid() . '.' . $fileExt;
                        $filePath = $uploadDir . $newFileName;
                        
                        if (move_uploaded_file($fileTmp, $filePath)) {
                            $uploadedFiles[] = [
                                'original_name' => $fileName,
                                'file_name' => $newFileName,
                                'file_path' => $filePath,
                                'file_size' => $fileSize,
                                'uploaded_at' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                }
                
                if (!$error && !empty($uploadedFiles)) {
                    // Get existing files
                    $project = $db->fetchOne("SELECT file_uploads FROM projects WHERE id = ?", [$projectId]);
                    $existingFiles = $project['file_uploads'] ? json_decode($project['file_uploads'], true) : [];
                    
                    // Merge with new files
                    $allFiles = array_merge($existingFiles, $uploadedFiles);
                    
                    // Update project
                    $db->update('projects', ['file_uploads' => json_encode($allFiles)], 'id = ?', [$projectId]);
                    $auth->logAction($_SESSION['user_id'], 'upload', 'projects', $projectId, null, ['files' => count($uploadedFiles)]);
                    
                    $message = count($uploadedFiles) . ' file(s) uploaded successfully';
                }
            } else {
                $error = 'No files selected for upload';
            }
        } elseif ($action == 'delete_file') {
            $projectId = $_POST['project_id'];
            $fileIndex = $_POST['file_index'];
            
            $project = $db->fetchOne("SELECT file_uploads FROM projects WHERE id = ?", [$projectId]);
            $files = $project['file_uploads'] ? json_decode($project['file_uploads'], true) : [];
            
            if (isset($files[$fileIndex])) {
                // Delete physical file
                if (file_exists($files[$fileIndex]['file_path'])) {
                    unlink($files[$fileIndex]['file_path']);
                }
                
                // Remove from array
                array_splice($files, $fileIndex, 1);
                
                // Update database
                $db->update('projects', ['file_uploads' => json_encode($files)], 'id = ?', [$projectId]);
                $auth->logAction($_SESSION['user_id'], 'delete_file', 'projects', $projectId);
                
                $message = 'File deleted successfully';
            }
        }
    }
}

// Get projects with filters
$clientId = $_GET['client_id'] ?? '';
$status = $_GET['status'] ?? '';
$staffId = $_GET['staff_id'] ?? '';
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * RECORDS_PER_PAGE;

$whereConditions = [];
$params = [];

if ($clientId) {
    $whereConditions[] = "p.client_id = ?";
    $params[] = $clientId;
}

if ($status) {
    $whereConditions[] = "p.status = ?";
    $params[] = $status;
}

if ($staffId) {
    $whereConditions[] = "p.assigned_staff = ?";
    $params[] = $staffId;
}

// Staff can only see their own projects (unless admin/manager)
if (!$auth->hasRole(['admin', 'manager'])) {
    $whereConditions[] = "p.assigned_staff = ?";
    $params[] = $_SESSION['user_id'];
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$projects = $db->fetchAll("
    SELECT p.*, c.name as client_name, c.email as client_email, c.phone_number,
           u.name as staff_name
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_staff = u.id
    $whereClause
    ORDER BY p.updated_at DESC
    LIMIT " . RECORDS_PER_PAGE . " OFFSET $offset
", $params);

$totalProjects = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM projects p 
    JOIN clients c ON p.client_id = c.id 
    $whereClause
", $params)['count'];

$totalPages = ceil($totalProjects / RECORDS_PER_PAGE);

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
                <h1 class="h2">Projects</h1>
                <?php if ($auth->hasRole(['admin', 'manager'])): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="bi bi-plus"></i> Add Project
                </button>
                <?php endif; ?>
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
                        <?php if ($auth->hasRole(['admin', 'manager'])): ?>
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
                            <select name="staff_id" class="form-select">
                                <option value="">All Staff</option>
                                <?php foreach ($staff as $member): ?>
                                <option value="<?= $member['id'] ?>" <?= $staffId == $member['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($member['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in-progress" <?= $status == 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="on-hold" <?= $status == 'on-hold' ? 'selected' : '' ?>>On Hold</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary">Filter</button>
                            <a href="projects.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                        <div class="col-md-3 text-end">
                            <a href="export.php?type=projects<?= $clientId ? '&client_id=' . $clientId : '' ?><?= $status ? '&status=' . $status : '' ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> Export CSV
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Projects Grid -->
            <?php if (empty($projects)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-folder-x display-1 text-muted"></i>
                        <h4 class="mt-3">No Projects Found</h4>
                        <p class="text-muted">Start by creating a project for your clients.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($projects as $project): ?>
                    <?php 
                    $files = $project['file_uploads'] ? json_decode($project['file_uploads'], true) : [];
                    $fileCount = count($files);
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 border-left-<?= 
                            $project['status'] == 'completed' ? 'success' : 
                            ($project['status'] == 'in-progress' ? 'primary' : 
                            ($project['status'] == 'on-hold' ? 'warning' : 'secondary')) 
                        ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0"><?= htmlspecialchars($project['service']) ?></h6>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editProject(<?= htmlspecialchars(json_encode($project)) ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="showFiles(<?= $project['id'] ?>, '<?= htmlspecialchars($project['service']) ?>')">
                                                <i class="bi bi-files"></i> Files (<?= $fileCount ?>)
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item" href="payments.php?client_id=<?= $project['client_id'] ?>">
                                                <i class="bi bi-credit-card"></i> Add Payment
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">Client:</small>
                                    <div class="fw-bold"><?= htmlspecialchars($project['client_name']) ?></div>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">Status:</small>
                                    <div>
                                        <span class="badge bg-<?= 
                                            $project['status'] == 'completed' ? 'success' : 
                                            ($project['status'] == 'in-progress' ? 'primary' : 
                                            ($project['status'] == 'on-hold' ? 'warning' : 'secondary')) 
                                        ?>">
                                            <?= ucwords(str_replace('-', ' ', $project['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">Assigned Staff:</small>
                                    <div><?= $project['staff_name'] ? htmlspecialchars($project['staff_name']) : '<span class="text-muted">Unassigned</span>' ?></div>
                                </div>
                                
                                <?php if ($project['deliverables']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Deliverables:</small>
                                    <div class="small"><?= nl2br(htmlspecialchars($project['deliverables'])) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($project['notes']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Notes:</small>
                                    <div class="small"><?= nl2br(htmlspecialchars($project['notes'])) ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> 
                                        <?= date('M j, Y', strtotime($project['updated_at'])) ?>
                                    </small>
                                    <?php if ($fileCount > 0): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-paperclip"></i> <?= $fileCount ?> file<?= $fileCount != 1 ? 's' : '' ?>
                                    </small>
                                    <?php endif; ?>
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
                            <a class="page-link" href="?page=<?= $i ?><?= $clientId ? '&client_id=' . $clientId : '' ?><?= $status ? '&status=' . $status : '' ?><?= $staffId ? '&staff_id=' . $staffId : '' ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Add/Edit Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="projectForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add" id="formAction">
                    <input type="hidden" name="project_id" id="projectId">
                    
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
                                <label class="form-label">Service/Project Type *</label>
                                <input type="text" name="service" id="serviceInput" class="form-control" required placeholder="e.g., Website Design, Logo Creation, Photo Shoot">
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
                                    <option value="pending">Pending</option>
                                    <option value="in-progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="on-hold">On Hold</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="deliverablesDiv" style="display: none;">
                        <label class="form-label">Deliverables</label>
                        <textarea name="deliverables" id="deliverablesInput" class="form-control" rows="3" placeholder="List the expected deliverables for this project..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="notesInput" class="form-control" rows="3" placeholder="Project requirements, special instructions, or other details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- File Management Modal -->
<div class="modal fade" id="filesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filesModalTitle">Project Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" id="uploadForm" class="mb-4">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="project_id" id="uploadProjectId">
                    
                    <div class="mb-3">
                        <label class="form-label">Upload Files</label>
                        <input type="file" name="files[]" class="form-control" multiple accept="<?= implode(',', array_map(function($ext) { return '.' . $ext; }, UPLOAD_ALLOWED_TYPES)) ?>">
                        <div class="form-text">
                            Allowed types: <?= implode(', ', UPLOAD_ALLOWED_TYPES) ?>. 
                            Max size: <?= UPLOAD_MAX_SIZE / 1024 / 1024 ?>MB per file.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload Files
                    </button>
                </form>
                
                <hr>
                
                <!-- Files List -->
                <div id="filesList">
                    <!-- Files will be loaded here via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editProject(project) {
    document.getElementById('modalTitle').textContent = 'Edit Project';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Project';
    document.getElementById('projectId').value = project.id;
    document.getElementById('clientSelect').value = project.client_id;
    document.getElementById('clientSelect').disabled = true;
    document.getElementById('serviceInput').value = project.service;
    document.getElementById('staffSelect').value = project.assigned_staff || '';
    document.getElementById('statusSelect').value = project.status;
    document.getElementById('deliverablesInput').value = project.deliverables || '';
    document.getElementById('notesInput').value = project.notes || '';
    document.getElementById('statusDiv').style.display = 'block';
    document.getElementById('deliverablesDiv').style.display = 'block';
    
    new bootstrap.Modal(document.getElementById('addProjectModal')).show();
}

function showFiles(projectId, projectName) {
    document.getElementById('filesModalTitle').textContent = 'Files - ' + projectName;
    document.getElementById('uploadProjectId').value = projectId;
    
    // Load files via AJAX
    fetch('ajax/get_project_files.php?project_id=' + projectId)
        .then(response => response.json())
        .then(data => {
            let html = '';
            if (data.files && data.files.length > 0) {
                data.files.forEach((file, index) => {
                    const fileSize = (file.file_size / 1024).toFixed(1) + ' KB';
                    html += `
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-2">
                            <div>
                                <div class="fw-bold">${file.original_name}</div>
                                <small class="text-muted">${fileSize} - ${file.uploaded_at}</small>
                            </div>
                            <div>
                                <a href="download.php?project_id=${projectId}&file_index=${index}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFile(${projectId}, ${index})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                html = '<p class="text-muted text-center">No files uploaded yet.</p>';
            }
            document.getElementById('filesList').innerHTML = html;
        });
    
    new bootstrap.Modal(document.getElementById('filesModal')).show();
}

function deleteFile(projectId, fileIndex) {
    if (confirm('Are you sure you want to delete this file?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete_file">
            <input type="hidden" name="project_id" value="${projectId}">
            <input type="hidden" name="file_index" value="${fileIndex}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Reset form when modal is closed
document.getElementById('addProjectModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('projectForm').reset();
    document.getElementById('modalTitle').textContent = 'Add Project';
    document.getElementById('formAction').value = 'add';
    document.getElementById('submitBtn').textContent = 'Add Project';
    document.getElementById('projectId').value = '';
    document.getElementById('clientSelect').disabled = false;
    document.getElementById('statusDiv').style.display = 'none';
    document.getElementById('deliverablesDiv').style.display = 'none';
});
</script>

<?php include 'includes/footer.php'; ?>
