<?php
/**
 * File Download Handler
 */

require_once 'config/app.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$auth->requireLogin();

$projectId = $_GET['project_id'] ?? '';
$fileIndex = $_GET['file_index'] ?? '';

if (!$projectId || $fileIndex === '') {
    die('Invalid parameters');
}

$db = Database::getInstance();

// Check if user has access to this project
$whereClause = '';
$params = [$projectId];

if (!$auth->hasRole(['admin', 'manager'])) {
    $whereClause = 'AND assigned_staff = ?';
    $params[] = $_SESSION['user_id'];
}

$project = $db->fetchOne("SELECT file_uploads FROM projects WHERE id = ? $whereClause", $params);

if (!$project) {
    die('Project not found or access denied');
}

$files = $project['file_uploads'] ? json_decode($project['file_uploads'], true) : [];

if (!isset($files[$fileIndex])) {
    die('File not found');
}

$file = $files[$fileIndex];
$filePath = $file['file_path'];

if (!file_exists($filePath)) {
    die('File not found on server');
}

// Log the download
$auth->logAction($_SESSION['user_id'], 'download', 'projects', $projectId, null, ['file' => $file['original_name']]);

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file
readfile($filePath);
exit;
?>
