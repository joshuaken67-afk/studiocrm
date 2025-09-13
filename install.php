<?php
/**
 * StudioCRM Installer - Simplified Version
 * Auto-setup database and create admin account
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if already installed
if (file_exists('config/database.php') && file_exists('config/installed.lock')) {
    die('StudioCRM is already installed. Delete config/installed.lock to reinstall.');
}

$error = '';
$success = '';

if ($_POST) {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_name = trim($_POST['db_name'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    
    if (empty($db_host) || empty($db_name) || empty($db_user) || empty($admin_name) || empty($admin_email) || empty($admin_password)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            
            // Test database connection
            $dsn = "mysql:host=$db_host;charset=utf8";
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
            
            // Reconnect to the specific database
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8";
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
            
            $sql_statements = [
                "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    username VARCHAR(100) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'manager', 'staff', 'client') NOT NULL DEFAULT 'staff',
                    status ENUM('active', 'paused', 'terminated') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                
                "CREATE TABLE IF NOT EXISTS clients (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    phone_number VARCHAR(20) NOT NULL,
                    service_interest TEXT,
                    source VARCHAR(100),
                    notes TEXT,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                
                "CREATE TABLE IF NOT EXISTS clarity_forms (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    budget DECIMAL(10,2),
                    timeline VARCHAR(100),
                    preferred_contact ENUM('email', 'phone', 'text') DEFAULT 'email',
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
                )",
                
                "CREATE TABLE IF NOT EXISTS bookings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    service VARCHAR(255) NOT NULL,
                    booking_date DATE NOT NULL,
                    duration INT DEFAULT 60,
                    status ENUM('scheduled', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
                    assigned_staff INT,
                    notes TEXT,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_staff) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id)
                )",
                
                "CREATE TABLE IF NOT EXISTS projects (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    service VARCHAR(255) NOT NULL,
                    status ENUM('pending', 'in-progress', 'completed', 'on-hold') DEFAULT 'pending',
                    assigned_staff INT,
                    deliverables TEXT,
                    file_uploads TEXT,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                    FOREIGN KEY (assigned_staff) REFERENCES users(id) ON DELETE SET NULL
                )",
                
                "CREATE TABLE IF NOT EXISTS payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    payment_method ENUM('cash', 'card', 'bank_transfer', 'check') NOT NULL,
                    payment_date DATE NOT NULL,
                    description TEXT,
                    recorded_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                    FOREIGN KEY (recorded_by) REFERENCES users(id)
                )",
                
                "CREATE TABLE IF NOT EXISTS invoices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL,
                    invoice_number VARCHAR(50) UNIQUE NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    due_date DATE NOT NULL,
                    status ENUM('draft', 'sent', 'paid', 'overdue') DEFAULT 'draft',
                    pdf_file VARCHAR(255),
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id)
                )",
                
                "CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    table_name VARCHAR(50) NOT NULL,
                    record_id INT NOT NULL,
                    old_values TEXT,
                    new_values TEXT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )"
            ];
            
            // Execute each SQL statement
            foreach ($sql_statements as $sql) {
                $pdo->exec($sql);
            }
            
            // Create admin user
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, 'admin', 'active', NOW())");
            $stmt->execute([$admin_name, $admin_email, $admin_email, $hashed_password]);
            
            if (!is_dir('config')) {
                if (!mkdir('config', 0755, true)) {
                    throw new Exception('Could not create config directory');
                }
            }
            if (!is_dir('uploads')) {
                if (!mkdir('uploads', 0755, true)) {
                    throw new Exception('Could not create uploads directory');
                }
            }
            
            $config_content = "<?php\n";
            $config_content .= "define('DB_HOST', '" . addslashes($db_host) . "');\n";
            $config_content .= "define('DB_NAME', '" . addslashes($db_name) . "');\n";
            $config_content .= "define('DB_USER', '" . addslashes($db_user) . "');\n";
            $config_content .= "define('DB_PASS', '" . addslashes($db_pass) . "');\n";
            $config_content .= "define('APP_NAME', 'StudioCRM');\n";
            $config_content .= "?>";
            
            if (!file_put_contents('config/database.php', $config_content)) {
                throw new Exception('Could not write config file');
            }
            if (!file_put_contents('config/installed.lock', date('Y-m-d H:i:s'))) {
                throw new Exception('Could not create install lock file');
            }
            
            $success = 'StudioCRM installed successfully! <a href="index.php" class="btn btn-success">Login here</a>';
            
        } catch (Exception $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudioCRM Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">StudioCRM Installation</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h6>Database Setup Instructions:</h6>
                                <ul class="mb-0">
                                    <li>Create a MySQL database in your hosting control panel</li>
                                    <li>Note down the database host, name, username, and password</li>
                                    <li>Fill in the form below with your database credentials</li>
                                </ul>
                            </div>
                            
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Database Configuration</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Database Host *</label>
                                            <input type="text" name="db_host" class="form-control" placeholder="localhost or sql###.infinityfree.com" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Database Name *</label>
                                            <input type="text" name="db_name" class="form-control" placeholder="studiocrm" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Database Username *</label>
                                            <input type="text" name="db_user" class="form-control" placeholder="username" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Database Password</label>
                                            <input type="password" name="db_pass" class="form-control" placeholder="password">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>Admin Account</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Admin Name *</label>
                                            <input type="text" name="admin_name" class="form-control" placeholder="John Doe" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Admin Email *</label>
                                            <input type="email" name="admin_email" class="form-control" placeholder="admin@studio.com" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Admin Password *</label>
                                            <input type="password" name="admin_password" class="form-control" placeholder="Strong password" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 mt-3">Install StudioCRM</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
