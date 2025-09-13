<?php
/**
 * User Profile Management
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$message = '';
$error = '';

$user = $auth->getCurrentUser();

// Handle form submissions
if ($_POST) {
    if (!$auth->validateCSRFToken($_POST['_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action == 'update_profile') {
            $data = [
                'name' => $_POST['name'],
                'email' => $_POST['email']
            ];
            
            // Check if email is already taken by another user
            $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $user['id']]);
            if ($existingUser) {
                $error = 'Email address is already in use by another user';
            } else {
                try {
                    $oldData = $user;
                    $db->update('users', $data, 'id = ?', [$user['id']]);
                    $auth->logAction($user['id'], 'update_profile', 'users', $user['id'], $oldData, $data);
                    $message = 'Profile updated successfully';
                    
                    // Update session data
                    $_SESSION['user_name'] = $data['name'];
                    $user = $auth->getCurrentUser(); // Refresh user data
                } catch (Exception $e) {
                    $error = 'Error updating profile: ' . $e->getMessage();
                }
            }
        } elseif ($action == 'change_password') {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters long';
            } else {
                try {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $db->update('users', ['password' => $hashedPassword], 'id = ?', [$user['id']]);
                    $auth->logAction($user['id'], 'change_password', 'users', $user['id']);
                    $message = 'Password changed successfully';
                } catch (Exception $e) {
                    $error = 'Error changing password: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get user activity logs
$activityLogs = $db->fetchAll("
    SELECT action, table_name, timestamp 
    FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY timestamp DESC 
    LIMIT 10
", [$user['id']]);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">My Profile</h1>
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

            <div class="row">
                <div class="col-lg-8">
                    <!-- Profile Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                            <div class="form-text">Username cannot be changed</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                                            <div class="form-text">Role is managed by administrators</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="_token" value="<?= $auth->generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Account Summary -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Account Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Member Since:</strong><br>
                                <?= date('F j, Y', strtotime($user['created_at'])) ?>
                            </div>
                            <div class="mb-3">
                                <strong>Last Updated:</strong><br>
                                <?= date('F j, Y g:i A', strtotime($user['updated_at'])) ?>
                            </div>
                            <div class="mb-3">
                                <strong>Account Status:</strong><br>
                                <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($activityLogs)): ?>
                                <?php foreach ($activityLogs as $log): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-3">
                                        <i class="bi bi-<?= 
                                            $log['action'] == 'login' ? 'box-arrow-in-right' : 
                                            ($log['action'] == 'create' ? 'plus-circle' : 
                                            ($log['action'] == 'update' ? 'pencil' : 'activity')) 
                                        ?> text-muted"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="small">
                                            <?= ucfirst($log['action']) ?> <?= $log['table_name'] ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= date('M j, g:i A', strtotime($log['timestamp'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted small">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
