<?php
/**
 * Staff Management Page
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireRole(['admin', 'manager']);

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
                'username' => $_POST['username'],
                'password' => $_POST['password'],
                'role' => $_POST['role'],
                'status' => 'active'
            ];
            
            try {
                $auth->createUser($data);
                $message = 'Staff member added successfully';
            } catch (Exception $e) {
                $error = 'Error adding staff: ' . $e->getMessage();
            }
        } elseif ($action == 'update_status') {
            $userId = $_POST['user_id'];
            $status = $_POST['status'];
            
            $oldUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            $db->update('users', ['status' => $status], 'id = ?', [$userId]);
            
            $auth->logAction($_SESSION['user_id'], 'update', 'users', $userId, $oldUser, ['status' => $status]);
            $message = 'Staff status updated successfully';
        }
    }
}

// Get staff list
$staff = $db->fetchAll("
    SELECT * FROM users 
    WHERE role IN ('staff', 'manager', 'admin') 
    ORDER BY created_at DESC
");

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Staff Management</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="bi bi-plus"></i> Add Staff
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

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $member): ?>
                                <tr>
                                    <td><?= htmlspecialchars($member['name']) ?></td>
                                    <td><?= htmlspecialchars($member['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $member['role'] == 'admin' ? 'danger' : ($member['role'] == 'manager' ? 'warning' : 'info') ?>">
                                            <?= ucfirst($member['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $member['status'] == 'active' ? 'success' : ($member['status'] == 'paused' ? 'warning' : 'danger') ?>">
                                            <?= ucfirst($member['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($member['created_at'])) ?></td>
                                    <td>
                                        <?php if ($member['id'] != $_SESSION['user_id']): ?>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="active" <?= $member['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="paused" <?= $member['status'] == 'paused' ? 'selected' : '' ?>>Paused</option>
                                                    <option value="terminated" <?= $member['status'] == 'terminated' ? 'selected' : '' ?>>Terminated</option>
                                                </select>
                                            </form>
                                        </div>
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

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="staff">Staff</option>
                            <option value="manager">Manager</option>
                            <?php if ($auth->hasRole('admin')): ?>
                            <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
