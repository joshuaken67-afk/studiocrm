<?php
/**
 * Signature Templates Management Page
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/signature-templates.php';

$auth = new Auth();
$auth->requireRole(['admin', 'manager']);

$db = Database::getInstance();
$signatureTemplates = new SignatureTemplates($db->getPdo());
$message = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (!$auth->validateCSRFToken($_POST['_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'create') {
            $data = [
                'template_name' => $_POST['template_name'],
                'template_type' => $_POST['template_type'],
                'html_content' => $_POST['html_content'],
                'css_styles' => $_POST['css_styles'],
                'signature_fields' => $_POST['signature_fields'] ?? [],
                'default_values' => $_POST['default_values'] ?? [],
                'created_by' => $_SESSION['user_id']
            ];
            
            $result = $signatureTemplates->createTemplate($data);
            
            if ($result['success']) {
                $message = 'Signature template created successfully';
                $auth->logAction($_SESSION['user_id'], 'create', 'signature_templates', $result['template_id']);
            } else {
                $error = $result['error'];
            }
        } elseif ($action == 'update') {
            $templateId = $_POST['template_id'];
            $data = [
                'template_name' => $_POST['template_name'],
                'template_type' => $_POST['template_type'],
                'html_content' => $_POST['html_content'],
                'css_styles' => $_POST['css_styles'],
                'signature_fields' => $_POST['signature_fields'] ?? [],
                'default_values' => $_POST['default_values'] ?? []
            ];
            
            $result = $signatureTemplates->updateTemplate($templateId, $data);
            
            if ($result['success']) {
                $message = 'Signature template updated successfully';
                $auth->logAction($_SESSION['user_id'], 'update', 'signature_templates', $templateId);
            } else {
                $error = $result['error'];
            }
        } elseif ($action == 'delete') {
            $templateId = $_POST['template_id'];
            $result = $signatureTemplates->deleteTemplate($templateId);
            
            if ($result['success']) {
                $message = 'Signature template deleted successfully';
                $auth->logAction($_SESSION['user_id'], 'delete', 'signature_templates', $templateId);
            } else {
                $error = $result['error'];
            }
        } elseif ($action == 'create_defaults') {
            $signatureTemplates->createDefaultTemplates($_SESSION['user_id']);
            $message = 'Default signature templates created successfully';
        }
    }
}

// Get all templates
$templates = $signatureTemplates->getTemplates();
$templateTypes = $signatureTemplates->getTemplateTypes();
$usageStats = $signatureTemplates->getUsageStats();

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Signature Templates</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                        <i class="bi bi-plus"></i> Create Template
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="create_defaults">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-collection"></i> Create Defaults
                        </button>
                    </form>
                </div>
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

            <!-- Templates Grid -->
            <?php if (empty($templates)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-file-earmark-text display-1 text-muted"></i>
                        <h4 class="mt-3">No Signature Templates</h4>
                        <p class="text-muted">Create your first signature template or use the default templates.</p>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="create_defaults">
                            <button type="submit" class="btn btn-primary">Create Default Templates</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($templates as $template): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title"><?= htmlspecialchars($template['template_name']) ?></h6>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="previewTemplate(<?= $template['id'] ?>)">
                                                <i class="bi bi-eye"></i> Preview
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteTemplate(<?= $template['id'] ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <span class="badge bg-primary"><?= $templateTypes[$template['template_type']] ?? ucfirst($template['template_type']) ?></span>
                                </div>
                                
                                <?php 
                                $fields = json_decode($template['signature_fields'], true);
                                if ($fields): 
                                ?>
                                <div class="mb-3">
                                    <small class="text-muted">Fields:</small>
                                    <div class="small">
                                        <?php foreach ($fields as $key => $label): ?>
                                        <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($label) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="small text-muted">
                                    <div><strong>Created:</strong> <?= date('M j, Y', strtotime($template['created_at'])) ?></div>
                                    <?php if ($template['updated_at']): ?>
                                    <div><strong>Updated:</strong> <?= date('M j, Y', strtotime($template['updated_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Usage Statistics -->
            <?php if (!empty($usageStats)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5>Template Usage Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Type</th>
                                    <th>Usage Count</th>
                                    <th>Last Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usageStats as $stat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($stat['template_name']) ?></td>
                                    <td><?= $templateTypes[$stat['template_type']] ?? ucfirst($stat['template_type']) ?></td>
                                    <td><?= $stat['usage_count'] ?></td>
                                    <td><?= $stat['last_used'] ? date('M j, Y', strtotime($stat['last_used'])) : 'Never' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" id="templateForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Create Signature Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="create" id="formAction">
                    <input type="hidden" name="template_id" id="templateId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Template Name *</label>
                                <input type="text" name="template_name" id="templateName" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Template Type *</label>
                                <select name="template_type" id="templateType" class="form-select" required>
                                    <?php foreach ($templateTypes as $key => $name): ?>
                                    <option value="<?= $key ?>"><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">HTML Content *</label>
                        <textarea name="html_content" id="htmlContent" class="form-control" rows="8" required placeholder="Enter HTML content with placeholders like {{field_name}}"></textarea>
                        <div class="form-text">Use {{field_name}} for dynamic content placeholders</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">CSS Styles</label>
                        <textarea name="css_styles" id="cssStyles" class="form-control" rows="6" placeholder="Enter CSS styles for the signature block"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Signature Fields (JSON)</label>
                                <textarea name="signature_fields" id="signatureFields" class="form-control" rows="4" placeholder='{"field_name": "Field Label"}'></textarea>
                                <div class="form-text">Define available fields as JSON object</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Default Values (JSON)</label>
                                <textarea name="default_values" id="defaultValues" class="form-control" rows="4" placeholder='{"field_name": "Default Value"}'></textarea>
                                <div class="form-text">Set default values for fields</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Preview</label>
                        <div id="templatePreview" class="border p-3 bg-light" style="min-height: 100px;">
                            <em class="text-muted">Preview will appear here...</em>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" onclick="previewCurrentTemplate()">Preview</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Create Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>
</div>

<script>
function editTemplate(template) {
    document.getElementById('modalTitle').textContent = 'Edit Signature Template';
    document.getElementById('formAction').value = 'update';
    document.getElementById('submitBtn').textContent = 'Update Template';
    document.getElementById('templateId').value = template.id;
    document.getElementById('templateName').value = template.template_name;
    document.getElementById('templateType').value = template.template_type;
    document.getElementById('htmlContent').value = template.html_content;
    document.getElementById('cssStyles').value = template.css_styles;
    document.getElementById('signatureFields').value = template.signature_fields;
    document.getElementById('defaultValues').value = template.default_values;
    
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this signature template?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="template_id" value="${templateId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function previewTemplate(templateId) {
    fetch(`ajax/preview-signature-template.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('previewContent').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('previewModal')).show();
            } else {
                alert('Error loading preview: ' + data.error);
            }
        });
}

function previewCurrentTemplate() {
    const html = document.getElementById('htmlContent').value;
    const css = document.getElementById('cssStyles').value;
    
    // Simple preview with sample data
    let previewHtml = html;
    previewHtml = previewHtml.replace(/\{\{([^}]+)\}\}/g, '<span style="background: yellow; padding: 2px;">$1</span>');
    
    const fullPreview = `<style>${css}</style>${previewHtml}`;
    document.getElementById('templatePreview').innerHTML = fullPreview;
}

// Reset form when modal is closed
document.getElementById('templateModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('templateForm').reset();
    document.getElementById('modalTitle').textContent = 'Create Signature Template';
    document.getElementById('formAction').value = 'create';
    document.getElementById('submitBtn').textContent = 'Create Template';
    document.getElementById('templateId').value = '';
    document.getElementById('templatePreview').innerHTML = '<em class="text-muted">Preview will appear here...</em>';
});
</script>

<?php include 'includes/footer.php'; ?>