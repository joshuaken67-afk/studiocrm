<?php
/**
 * Document Management Page
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/document-manager.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$docManager = new DocumentManager($db->getPdo());
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (!$auth->validateCSRFToken($_POST['_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'upload') {
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $metadata = [
                    'document_type' => $_POST['document_type'],
                    'category' => $_POST['category'],
                    'description' => $_POST['description'],
                    'tags' => $_POST['tags'],
                    'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
                    'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null,
                    'uploaded_by' => $_SESSION['user_id']
                ];
                
                $result = $docManager->uploadDocument($_FILES['document'], $metadata);
                
                if ($result['success']) {
                    $message = 'Document uploaded successfully';
                    $auth->logAction($_SESSION['user_id'], 'upload', 'documents', $result['document_id']);
                } else {
                    $error = $result['error'];
                }
            } else {
                $error = 'Please select a file to upload';
            }
        } elseif ($action == 'update') {
            $documentId = $_POST['document_id'];
            $metadata = [
                'document_type' => $_POST['document_type'],
                'category' => $_POST['category'],
                'description' => $_POST['description'],
                'tags' => $_POST['tags'],
                'project_id' => !empty($_POST['project_id']) ? $_POST['project_id'] : null,
                'client_id' => !empty($_POST['client_id']) ? $_POST['client_id'] : null
            ];
            
            $result = $docManager->updateDocument($documentId, $metadata);
            
            if ($result['success']) {
                $message = 'Document updated successfully';
                $auth->logAction($_SESSION['user_id'], 'update', 'documents', $documentId);
            } else {
                $error = $result['error'];
            }
        } elseif ($action == 'delete') {
            $documentId = $_POST['document_id'];
            $result = $docManager->deleteDocument($documentId);
            
            if ($result['success']) {
                $message = 'Document deleted successfully';
                $auth->logAction($_SESSION['user_id'], 'delete', 'documents', $documentId);
            } else {
                $error = $result['error'];
            }
        }
    }
}

// Get filters
$filters = [
    'category' => $_GET['category'] ?? '',
    'document_type' => $_GET['document_type'] ?? '',
    'client_id' => $_GET['client_id'] ?? '',
    'project_id' => $_GET['project_id'] ?? '',
    'search' => $_GET['search'] ?? '',
    'limit' => 50
];

// Get documents
$documents = $docManager->getDocuments($filters);

// Get data for dropdowns
$categories = $docManager->getCategories();
$documentTypes = $docManager->getDocumentTypes();
$clients = $db->fetchAll("SELECT id, name FROM clients WHERE status = 'active' ORDER BY name");
$projects = $db->fetchAll("SELECT p.id, p.service, c.name as client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id ORDER BY p.created_at DESC");

// Get storage statistics
$storageStats = $docManager->getStorageStats();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Document Management</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="bi bi-upload"></i> Upload Document
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

            <!-- Storage Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Documents</h5>
                            <h3><?= number_format($storageStats['total']['total_documents']) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Storage Used</h5>
                            <h3><?= number_format($storageStats['total']['total_size'] / 1024 / 1024, 1) ?> MB</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Average Size</h5>
                            <h3><?= number_format($storageStats['total']['avg_size'] / 1024, 1) ?> KB</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h5 class="card-title">Categories</h5>
                            <h3><?= count($storageStats['by_category']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $key => $name): ?>
                                <option value="<?= $key ?>" <?= $filters['category'] == $key ? 'selected' : '' ?>>
                                    <?= $name ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="document_type" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($documentTypes as $key => $name): ?>
                                <option value="<?= $key ?>" <?= $filters['document_type'] == $key ? 'selected' : '' ?>>
                                    <?= $name ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="client_id" class="form-select">
                                <option value="">All Clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>" <?= $filters['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($client['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search documents..." value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary">Filter</button>
                            <a href="documents.php" class="btn btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documents Grid -->
            <?php if (empty($documents)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-earmark-x display-1 text-muted"></i>
                        <h4 class="mt-3">No Documents Found</h4>
                        <p class="text-muted">Upload your first document to get started.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($documents as $document): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="file-icon">
                                        <i class="bi bi-file-earmark-<?= 
                                            in_array($document['file_type'], ['pdf']) ? 'pdf' : 
                                            (in_array($document['file_type'], ['doc', 'docx']) ? 'word' : 
                                            (in_array($document['file_type'], ['xls', 'xlsx']) ? 'excel' : 
                                            (in_array($document['file_type'], ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'text'))) 
                                        ?> fs-1 text-primary"></i>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="<?= $docManager->getDownloadUrl($document['id']) ?>">
                                                <i class="bi bi-download"></i> Download
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="editDocument(<?= htmlspecialchars(json_encode($document)) ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteDocument(<?= $document['id'] ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <h6 class="card-title"><?= htmlspecialchars($document['original_name']) ?></h6>
                                
                                <div class="mb-2">
                                    <span class="badge bg-secondary"><?= $categories[$document['category']] ?? 'Uncategorized' ?></span>
                                    <span class="badge bg-info"><?= strtoupper($document['file_type']) ?></span>
                                </div>
                                
                                <?php if ($document['description']): ?>
                                <p class="card-text small text-muted"><?= htmlspecialchars($document['description']) ?></p>
                                <?php endif; ?>
                                
                                <div class="small text-muted">
                                    <div><strong>Size:</strong> <?= number_format($document['file_size'] / 1024, 1) ?> KB</div>
                                    <?php if ($document['client_name']): ?>
                                    <div><strong>Client:</strong> <?= htmlspecialchars($document['client_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($document['project_name']): ?>
                                    <div><strong>Project:</strong> <?= htmlspecialchars($document['project_name']) ?></div>
                                    <?php endif; ?>
                                    <div><strong>Uploaded:</strong> <?= date('M j, Y', strtotime($document['created_at'])) ?></div>
                                    <div><strong>By:</strong> <?= htmlspecialchars($document['uploaded_by_name']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="upload" id="formAction">
                    <input type="hidden" name="document_id" id="documentId">
                    
                    <div class="mb-3" id="fileUploadDiv">
                        <label class="form-label">Select File *</label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" required>
                        <div class="form-text">Allowed types: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF. Max size: 10MB</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Document Type *</label>
                                <select name="document_type" id="documentType" class="form-select" required>
                                    <?php foreach ($documentTypes as $key => $name): ?>
                                    <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category" id="category" class="form-select" required>
                                    <?php foreach ($categories as $key => $name): ?>
                                    <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Client</label>
                                <select name="client_id" id="clientId" class="form-select">
                                    <option value="">No specific client</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Project</label>
                                <select name="project_id" id="projectId" class="form-select">
                                    <option value="">No specific project</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['service'] . ' - ' . $project['client_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Brief description of the document..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" id="tags" class="form-control" placeholder="Comma-separated tags (e.g., contract, signed, final)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDocument(document) {
    document.getElementById('modalTitle').textContent = 'Edit Document';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Document';
    document.getElementById('documentId').value = document.id;
    document.getElementById('documentType').value = document.document_type;
    document.getElementById('category').value = document.category;
    document.getElementById('clientId').value = document.client_id || '';
    document.getElementById('projectId').value = document.project_id || '';
    document.getElementById('description').value = document.description || '';
    document.getElementById('tags').value = document.tags || '';
    document.getElementById('fileUploadDiv').style.display = 'none';
    
    new bootstrap.Modal(document.getElementById('uploadModal')).show();
}

function deleteDocument(documentId) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="document_id" value="${documentId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Reset form when modal is closed
document.getElementById('uploadModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('uploadForm').reset();
    document.getElementById('modalTitle').textContent = 'Upload Document';
    document.getElementById('formAction').value = 'upload';
    document.getElementById('submitBtn').textContent = 'Upload Document';
    document.getElementById('documentId').value = '';
    document.getElementById('fileUploadDiv').style.display = 'block';
});
</script>

<?php include 'includes/footer.php'; ?>